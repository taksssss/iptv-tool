package handler

import (
	"net/http"
	"strings"

	"github.com/gin-gonic/gin"
	"github.com/taksssss/iptv-tool/go-service/internal/epg"
	"github.com/taksssss/iptv-tool/go-service/pkg/config"
)

// IconHandler redirects to channel logo URLs.
type IconHandler struct {
	norm *epg.Normaliser
	cfg  *config.Config
}

// NewIconHandler constructs an IconHandler.
func NewIconHandler(norm *epg.Normaliser, cfg *config.Config) *IconHandler {
	return &IconHandler{norm: norm, cfg: cfg}
}

// ServeIcon handles GET /?ch=<channel>&type=icon
func (h *IconHandler) ServeIcon(c *gin.Context) {
	ch := c.Query("ch")
	cleanCh := h.norm.Clean(ch)

	iconURL := h.norm.IconURL([]string{cleanCh, ch})
	if iconURL == "" {
		c.String(http.StatusNotFound, "Icon not found")
		return
	}

	// Rewrite relative icon paths to absolute
	if strings.HasPrefix(iconURL, "/data/icon/") {
		serverURL := h.cfg.ServerURL
		if serverURL == "" {
			serverURL = detectServerURL(c.Request)
		}
		iconURL = serverURL + iconURL
	}

	c.Redirect(http.StatusFound, iconURL)
}
