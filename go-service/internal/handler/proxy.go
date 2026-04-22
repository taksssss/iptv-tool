package handler

import (
	"net/http"
	"net/url"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/taksssss/iptv-tool/go-service/internal/stream"
	"github.com/taksssss/iptv-tool/go-service/pkg/config"
)

// ProxyHandler forwards stream requests to upstream sources.
type ProxyHandler struct {
	proxy *stream.Proxy
	cfg   *config.Config
}

// NewProxyHandler constructs a ProxyHandler.
func NewProxyHandler(proxy *stream.Proxy, cfg *config.Config) *ProxyHandler {
	return &ProxyHandler{proxy: proxy, cfg: cfg}
}

// ServeProxy handles GET /proxy?url=<encrypted_url>
//
// The URL is encrypted with AES-256-CBC using the proxy token.
// If the proxy token is empty, the URL is treated as plain text.
func (h *ProxyHandler) ServeProxy(c *gin.Context) {
	encURL := c.Query("url")
	if encURL == "" {
		c.String(http.StatusForbidden, "Forbidden: Missing URL")
		return
	}

	var upstreamURL string
	if h.cfg.ProxyToken != "" {
		decoded, err := stream.DecryptURL(encURL, h.cfg.ProxyToken)
		if err != nil {
			c.String(http.StatusForbidden, "Forbidden: Invalid URL")
			return
		}
		upstreamURL = decoded
	} else {
		upstreamURL = encURL
	}

	// Restrict to http/https schemes to prevent SSRF to internal services.
	if !isHTTPURL(upstreamURL) {
		c.String(http.StatusBadRequest, "Bad Request: unsupported URL scheme")
		return
	}

	// Handle #NOPROXY marker: redirect instead of proxy
	if strings.HasSuffix(upstreamURL, "#NOPROXY") {
		upstreamURL = strings.TrimSuffix(upstreamURL, "#NOPROXY")
		c.Redirect(http.StatusFound, upstreamURL)
		return
	}

	// Append any additional query params (except url)
	extra := buildExtraParams(c)
	if extra != "" {
		sep := "?"
		if strings.Contains(upstreamURL, "?") {
			sep = "&"
		}
		upstreamURL = upstreamURL + sep + extra
	}

	serverURL := h.cfg.ServerURL
	if serverURL == "" {
		serverURL = detectServerURL(c.Request)
	}
	h.proxy.SetProxyBase(serverURL + "/proxy?url=")
	h.proxy.ServeStream(c.Request.Context(), c.Writer, c.Request, upstreamURL, h.cfg.ProxyToken)
}

// buildExtraParams serialises all query params except "url".
func buildExtraParams(c *gin.Context) string {
	q := c.Request.URL.Query()
	q.Del("url")
	return q.Encode()
}

// isHTTPURL returns true if rawURL has an http or https scheme.
func isHTTPURL(rawURL string) bool {
	u, err := url.Parse(rawURL)
	if err != nil {
		return false
	}
	s := strings.ToLower(u.Scheme)
	return s == "http" || s == "https"
}
