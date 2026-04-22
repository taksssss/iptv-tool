// Package stream implements the zero-copy IPTV stream proxy.
//
// Design:
//   - Uses a single shared *http.Client with a custom transport (connection pool).
//   - Forwards only safe upstream headers; strips hop-by-hop headers.
//   - For TS/binary streams: io.Copy → immediate zero-copy pipe to client.
//   - For HLS (m3u8): buffers the manifest, rewrites segment URLs through proxy,
//     then flushes to the client.
//   - Supports context cancellation (client disconnect aborts upstream read).
//   - URL encryption uses AES-256-CBC compatible with the PHP implementation.
package stream

import (
	"bufio"
	"bytes"
	"context"
	"crypto/aes"
	"crypto/cipher"
	"crypto/md5"  //nolint:gosec // PHP compat: non-crypto IV derivation
	"crypto/sha256"
	"encoding/base64"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"regexp"
	"strings"
	"sync"
)

// hopByHopHeaders are stripped from both request and response.
var hopByHopHeaders = map[string]struct{}{
	"Connection":          {},
	"Keep-Alive":          {},
	"Proxy-Authenticate":  {},
	"Proxy-Authorization": {},
	"Te":                  {},
	"Trailers":            {},
	"Transfer-Encoding":   {},
	"Upgrade":             {},
}

// Proxy is the core streaming proxy.
type Proxy struct {
	client    *http.Client
	mu        sync.Mutex
	proxyBase string // e.g. "http://host/proxy?url="
}

// NewProxy constructs a Proxy with the given HTTP client and self-referencing base URL.
func NewProxy(client *http.Client, proxyBase string) *Proxy {
	return &Proxy{client: client, proxyBase: proxyBase}
}

// SetProxyBase updates the proxy base URL (called on each request so the host is correct).
func (p *Proxy) SetProxyBase(base string) {
	p.mu.Lock()
	p.proxyBase = base
	p.mu.Unlock()
}

func (p *Proxy) getProxyBase() string {
	p.mu.Lock()
	defer p.mu.Unlock()
	return p.proxyBase
}

// ServeStream fetches the upstream URL and copies it to w.
// If the content is HLS (m3u8), segment URLs are rewritten through the proxy.
// token is used to encrypt rewritten URLs for m3u8.
// upstreamURL must be a validated http/https URL (callers are responsible).
func (p *Proxy) ServeStream(ctx context.Context, w http.ResponseWriter, r *http.Request, upstreamURL, token string) {
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, upstreamURL, nil)
	if err != nil {
		http.Error(w, "bad upstream url", http.StatusBadRequest)
		return
	}

	// Forward safe client headers
	copyRequestHeaders(r, req)

	resp, err := p.client.Do(req)
	if err != nil {
		http.Error(w, fmt.Sprintf("upstream error: %v", err), http.StatusBadGateway)
		return
	}
	defer resp.Body.Close()

	// Copy safe response headers
	copyResponseHeaders(resp, w)
	w.WriteHeader(resp.StatusCode)

	ct := resp.Header.Get("Content-Type")
	isM3U8 := strings.Contains(ct, "mpegurl") ||
		strings.HasSuffix(strings.ToLower(upstreamURL), ".m3u8")

	if isM3U8 {
		p.serveM3U8(ctx, w, resp, upstreamURL, token)
	} else {		// Zero-copy pipe for TS/raw streams
		if fw, ok := w.(http.Flusher); ok {
			// Wrap io.Copy to flush after each write
			io.Copy(&flushedWriter{w: w, f: fw}, resp.Body) //nolint:errcheck
		} else {
			io.Copy(w, resp.Body) //nolint:errcheck
		}
	}
}

// serveM3U8 buffers the manifest, rewrites segment/playlist URLs, and writes to w.
func (p *Proxy) serveM3U8(_ context.Context, w http.ResponseWriter, resp *http.Response, originalURL, token string) {
	base := extractBase(originalURL)
	proxyBase := p.getProxyBase()

	w.Header().Set("Content-Type", "application/vnd.apple.mpegurl")

	scanner := bufio.NewScanner(resp.Body)
	var buf bytes.Buffer
	segRe := regexp.MustCompile(`^[^#\s]`)

	for scanner.Scan() {
		line := scanner.Text()
		if segRe.MatchString(line) {
			line = p.rewriteSegmentURL(line, base, token, proxyBase)
		} else if strings.HasPrefix(line, "#EXT-X-KEY:") {
			// Rewrite key URI inline
			line = rewriteKeyURI(line, func(u string) string {
				return p.rewriteSegmentURL(u, base, token, proxyBase)
			})
		}
		buf.WriteString(line)
		buf.WriteByte('\n')
	}

	w.Header().Set("Content-Length", fmt.Sprintf("%d", buf.Len()))
	w.Write(buf.Bytes()) //nolint:errcheck
}

// rewriteSegmentURL makes a segment URL absolute, then wraps it in a proxy URL.
func (p *Proxy) rewriteSegmentURL(link, base, token, proxyBase string) string {
	link = strings.TrimSpace(link)
	if link == "" {
		return link
	}
	if !strings.HasPrefix(link, "http://") && !strings.HasPrefix(link, "https://") {
		link = base + link
	}
	enc := EncryptURL(link, token)
	return proxyBase + url.QueryEscape(enc)
}

// copyRequestHeaders copies non-hop-by-hop headers from src to dst,
// excluding Host and Accept-Encoding.
func copyRequestHeaders(src *http.Request, dst *http.Request) {
	for k, vv := range src.Header {
		lower := strings.ToLower(k)
		if lower == "host" || lower == "accept-encoding" {
			continue
		}
		if _, hop := hopByHopHeaders[k]; hop {
			continue
		}
		for _, v := range vv {
			dst.Header.Add(k, v)
		}
	}
}

// copyResponseHeaders copies safe response headers to the ResponseWriter.
func copyResponseHeaders(src *http.Response, dst http.ResponseWriter) {
	for k, vv := range src.Header {
		if _, hop := hopByHopHeaders[k]; hop {
			continue
		}
		for _, v := range vv {
			dst.Header().Add(k, v)
		}
	}
}

// extractBase returns the directory portion of a URL (for relative segment resolution).
func extractBase(rawURL string) string {
	u, err := url.Parse(rawURL)
	if err != nil {
		return ""
	}
	path := u.Path
	if idx := strings.LastIndex(path, "/"); idx >= 0 {
		u.Path = path[:idx+1]
	}
	u.RawQuery = ""
	u.Fragment = ""
	return u.String()
}

// rewriteKeyURI rewrites the URI= attribute in an #EXT-X-KEY line.
func rewriteKeyURI(line string, rewrite func(string) string) string {
	re := regexp.MustCompile(`URI="([^"]+)"`)
	return re.ReplaceAllStringFunc(line, func(match string) string {
		inner := re.FindStringSubmatch(match)
		if len(inner) < 2 {
			return match
		}
		return `URI="` + rewrite(inner[1]) + `"`
	})
}

// flushedWriter wraps a ResponseWriter and flushes after each Write call.
type flushedWriter struct {
	w http.ResponseWriter
	f http.Flusher
}

func (fw *flushedWriter) Write(p []byte) (int, error) {
	n, err := fw.w.Write(p)
	fw.f.Flush()
	return n, err
}

// ---- URL Encryption (AES-256-CBC, PHP-compatible) -----------------------

// EncryptURL encrypts a URL using AES-256-CBC with the PHP-compatible
// key/IV derivation:  key = sha256(token)[0:32], iv = md5(token)[0:16].
func EncryptURL(rawURL, token string) string {
	key, iv := deriveKeyIV(token)
	block, err := aes.NewCipher(key)
	if err != nil {
		return rawURL
	}
	plaintext := pkcs7Pad([]byte(rawURL), aes.BlockSize)
	ciphertext := make([]byte, len(plaintext))
	cipher.NewCBCEncrypter(block, iv).CryptBlocks(ciphertext, plaintext)
	return base64.StdEncoding.EncodeToString(ciphertext)
}

// DecryptURL decrypts a URL previously encrypted with EncryptURL.
func DecryptURL(enc, token string) (string, error) {
	data, err := base64.StdEncoding.DecodeString(enc)
	if err != nil {
		return "", fmt.Errorf("base64 decode: %w", err)
	}
	key, iv := deriveKeyIV(token)
	block, err := aes.NewCipher(key)
	if err != nil {
		return "", fmt.Errorf("new cipher: %w", err)
	}
	if len(data)%aes.BlockSize != 0 {
		return "", fmt.Errorf("ciphertext not block-aligned")
	}
	dst := make([]byte, len(data))
	cipher.NewCBCDecrypter(block, iv).CryptBlocks(dst, data)
	dst, err = pkcs7Unpad(dst)
	if err != nil {
		return "", err
	}
	return string(dst), nil
}

// deriveKeyIV derives the AES key and IV from token using the same logic as PHP.
//
//	key = hex(sha256(token))[0:32]  → 32 bytes
//	iv  = hex(md5(token))[0:16]     → 16 bytes
func deriveKeyIV(token string) (key, iv []byte) {
	hSHA := sha256.Sum256([]byte(token))
	hMD5 := md5.Sum([]byte(token)) //nolint:gosec // PHP compat
	// PHP hash() returns lowercase hex string; we convert similarly.
	keyHex := fmt.Sprintf("%x", hSHA)
	ivHex := fmt.Sprintf("%x", hMD5)
	return []byte(keyHex[:32]), []byte(ivHex[:16])
}

func pkcs7Pad(b []byte, blockSize int) []byte {
	pad := blockSize - len(b)%blockSize
	padding := bytes.Repeat([]byte{byte(pad)}, pad)
	return append(b, padding...)
}

func pkcs7Unpad(b []byte) ([]byte, error) {
	if len(b) == 0 {
		return nil, fmt.Errorf("empty input")
	}
	pad := int(b[len(b)-1])
	if pad == 0 || pad > aes.BlockSize || pad > len(b) {
		return nil, fmt.Errorf("invalid padding %d", pad)
	}
	return b[:len(b)-pad], nil
}
