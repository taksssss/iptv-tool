package handlers

import (
	"io"
	"net/http"
	"net/url"
	"strings"

	"github.com/taksssss/iptv-tool/internal/config"
	"github.com/taksssss/iptv-tool/internal/database"
)

// ProxyHandler handles proxy requests for streaming
type ProxyHandler struct {
	cfg *config.Config
	db  *database.DB
}

// NewProxyHandler creates a new proxy handler
func NewProxyHandler(cfg *config.Config, db *database.DB) *ProxyHandler {
	return &ProxyHandler{
		cfg: cfg,
		db:  db,
	}
}

// Handle handles proxy requests
func (h *ProxyHandler) Handle(w http.ResponseWriter, r *http.Request) {
	// Get encrypted URL parameter
	encURL := r.URL.Query().Get("url")
	if encURL == "" {
		http.Error(w, "Forbidden: Missing URL", http.StatusForbidden)
		return
	}

	// Decrypt URL (TODO: implement proper decryption)
	targetURL, err := url.QueryUnescape(encURL)
	if err != nil {
		http.Error(w, "Forbidden: Invalid URL", http.StatusForbidden)
		return
	}

	// Check for NOPROXY marker
	noProxy := false
	if strings.HasSuffix(targetURL, "#NOPROXY") {
		noProxy = true
		targetURL = strings.TrimSuffix(targetURL, "#NOPROXY")
	}

	// If NOPROXY, just redirect
	if noProxy {
		http.Redirect(w, r, targetURL, http.StatusFound)
		return
	}

	// Proxy the request
	client := &http.Client{}
	proxyReq, err := http.NewRequest("GET", targetURL, nil)
	if err != nil {
		http.Error(w, "Bad Gateway", http.StatusBadGateway)
		return
	}

	// Copy headers from original request (excluding Host and Accept-Encoding)
	for key, values := range r.Header {
		if strings.ToLower(key) != "host" && strings.ToLower(key) != "accept-encoding" {
			for _, value := range values {
				proxyReq.Header.Add(key, value)
			}
		}
	}

	// Execute request
	resp, err := client.Do(proxyReq)
	if err != nil {
		http.Error(w, "Bad Gateway: "+err.Error(), http.StatusBadGateway)
		return
	}
	defer resp.Body.Close()

	// Copy response headers
	for key, values := range resp.Header {
		if strings.ToLower(key) != "transfer-encoding" {
			for _, value := range values {
				w.Header().Add(key, value)
			}
		}
	}

	// Set status code
	w.WriteHeader(resp.StatusCode)

	// Check if it's M3U8
	contentType := resp.Header.Get("Content-Type")
	isM3U8 := strings.Contains(strings.ToLower(targetURL), ".m3u8") ||
		strings.Contains(strings.ToLower(contentType), "mpegurl")

	if isM3U8 {
		// Read entire M3U8 content
		body, err := io.ReadAll(resp.Body)
		if err != nil {
			return
		}

		// Process M3U8 content (replace URLs with proxied URLs)
		content := string(body)
		// TODO: Implement M3U8 URL rewriting

		w.Write([]byte(content))
	} else {
		// Stream content directly for TS segments
		io.Copy(w, resp.Body)
	}
}
