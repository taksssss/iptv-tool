package handler

import (
	"bytes"
	"compress/gzip"
	"net/http"
	"os"
	"path/filepath"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/taksssss/iptv-tool/go-service/internal/model"
	"github.com/taksssss/iptv-tool/go-service/internal/playlist"
	"github.com/taksssss/iptv-tool/go-service/internal/repository"
	"github.com/taksssss/iptv-tool/go-service/internal/service"
	"github.com/taksssss/iptv-tool/go-service/internal/stream"
	"github.com/taksssss/iptv-tool/go-service/pkg/cache"
	"github.com/taksssss/iptv-tool/go-service/pkg/config"
)

// PlaylistHandler serves M3U playlists and XMLTV EPG files.
type PlaylistHandler struct {
	channelSvc *service.ChannelService
	epgRepo    *repository.EPGRepo
	cache      cache.Cache
	cfg        *config.Config
	dataDir    string
}

// NewPlaylistHandler constructs a PlaylistHandler.
func NewPlaylistHandler(
	ch *service.ChannelService,
	epg *repository.EPGRepo,
	c cache.Cache,
	cfg *config.Config,
) *PlaylistHandler {
	return &PlaylistHandler{
		channelSvc: ch,
		epgRepo:    epg,
		cache:      c,
		cfg:        cfg,
		dataDir:    cfg.DataDir,
	}
}

// ServeM3U handles GET /playlist.m3u
func (h *PlaylistHandler) ServeM3U(c *gin.Context) {
	ctx := c.Request.Context()
	cacheKey := "playlist:m3u"

	if hit, ok := h.cache.Get(ctx, cacheKey); ok {
		c.Header("Access-Control-Allow-Origin", "*")
		c.Data(http.StatusOK, "application/x-mpegurl; charset=utf-8", []byte(hit))
		return
	}

	channels, err := h.channelSvc.ListForConfig(ctx, "default")
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}

	opts := h.buildM3UOpts(c, channels)
	content := playlist.GenerateM3U(channels, opts)

	_ = h.cache.Set(ctx, cacheKey, content, 10*time.Minute)

	c.Header("Access-Control-Allow-Origin", "*")
	c.Data(http.StatusOK, "application/x-mpegurl; charset=utf-8", []byte(content))
}

// ServeTXT handles GET /playlist.txt
func (h *PlaylistHandler) ServeTXT(c *gin.Context) {
	ctx := c.Request.Context()
	cacheKey := "playlist:txt"

	if hit, ok := h.cache.Get(ctx, cacheKey); ok {
		c.Header("Access-Control-Allow-Origin", "*")
		c.Data(http.StatusOK, "text/plain; charset=utf-8", []byte(hit))
		return
	}

	channels, err := h.channelSvc.ListForConfig(ctx, "default")
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}

	opts := h.buildM3UOpts(c, channels)
	content := playlist.GenerateTXT(channels, opts)

	_ = h.cache.Set(ctx, cacheKey, content, 10*time.Minute)

	c.Header("Access-Control-Allow-Origin", "*")
	c.Data(http.StatusOK, "text/plain; charset=utf-8", []byte(content))
}

// ServeEPGXML handles GET /epg.xml  (uncompressed XMLTV)
func (h *PlaylistHandler) ServeEPGXML(c *gin.Context) {
	h.serveXML(c, false)
}

// ServeEPGGZ handles GET /epg.xml.gz  (gzip XMLTV)
func (h *PlaylistHandler) ServeEPGGZ(c *gin.Context) {
	h.serveXML(c, true)
}

func (h *PlaylistHandler) serveXML(c *gin.Context, gz bool) {
	ctx := c.Request.Context()
	cacheKey := "epg:xml"
	if gz {
		cacheKey = "epg:xml.gz"
	}

	// Try cache
	if hit, ok := h.cache.Get(ctx, cacheKey); ok {
		ct := "application/xml"
		if gz {
			ct = "application/gzip"
		}
		c.Header("Access-Control-Allow-Origin", "*")
		c.Data(http.StatusOK, ct, []byte(hit))
		return
	}

	// Try pre-generated files from PHP side
	xmlPath := filepath.Join(h.dataDir, "t.xml")
	gzPath := filepath.Join(h.dataDir, "t.xml.gz")

	if gz {
		if data, err := os.ReadFile(gzPath); err == nil {
			_ = h.cache.Set(ctx, cacheKey, string(data), 30*time.Minute)
			c.Header("Content-Disposition", "attachment; filename=\"t.xml.gz\"")
			c.Data(http.StatusOK, "application/gzip", data)
			return
		}
	} else {
		if data, err := os.ReadFile(xmlPath); err == nil {
			_ = h.cache.Set(ctx, cacheKey, string(data), 30*time.Minute)
			c.Data(http.StatusOK, "application/xml", data)
			return
		}
	}

	// Generate dynamically
	xmlData, err := h.generateXMLTV(c)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}

	if gz {
		var gzBuf []byte
		gzBuf, err = gzipCompress(xmlData)
		if err != nil {
			c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
			return
		}
		_ = h.cache.Set(ctx, cacheKey, string(gzBuf), 30*time.Minute)
		c.Header("Content-Disposition", "attachment; filename=\"t.xml.gz\"")
		c.Data(http.StatusOK, "application/gzip", gzBuf)
		return
	}

	_ = h.cache.Set(ctx, cacheKey, string(xmlData), 30*time.Minute)
	c.Data(http.StatusOK, "application/xml", xmlData)
}

// generateXMLTV builds XMLTV from today's EPG data.
func (h *PlaylistHandler) generateXMLTV(c *gin.Context) ([]byte, error) {
	ctx := c.Request.Context()
	today := time.Now().Format("2006-01-02")
	rows, err := h.epgRepo.AllForDate(ctx, today)
	if err != nil {
		return nil, err
	}

	epgByDate := map[string][]*model.EPGData{today: rows}
	opts := playlist.XMLTVOptions{Dates: []string{today}}

	// Optional channel filter from gen_list
	genChans, _ := h.epgRepo.ListGenChannels(ctx)
	opts.FilterChannels = genChans

	return playlist.GenerateXMLTV(epgByDate, opts)
}

// buildM3UOpts assembles M3UOptions from config and request.
func (h *PlaylistHandler) buildM3UOpts(c *gin.Context, _ []*model.Channel) playlist.M3UOptions {
	serverURL := h.cfg.ServerURL
	if serverURL == "" {
		serverURL = detectServerURL(c.Request)
	}

	token := c.Query("token")
	proxy := c.Query("proxy") != ""

	opts := playlist.M3UOptions{
		ServerURL:  serverURL,
		Token:      token,
		EPGPath:    "/epg.xml",
		ProxyMode:  proxy,
		ProxyToken: h.cfg.ProxyToken,
	}
	if proxy {
		opts.EncryptFn = stream.EncryptURL
	}
	return opts
}

// detectServerURL infers the server base URL from the request.
func detectServerURL(r *http.Request) string {
	scheme := "http"
	if r.TLS != nil || r.Header.Get("X-Forwarded-Proto") == "https" {
		scheme = "https"
	}
	host := r.Host
	if fh := r.Header.Get("X-Forwarded-Host"); fh != "" {
		host = fh
	}
	return scheme + "://" + host
}

// gzipCompress compresses data with gzip.
func gzipCompress(data []byte) ([]byte, error) {
	var bb bytes.Buffer
	w := gzip.NewWriter(&bb)
	if _, err := w.Write(data); err != nil {
		return nil, err
	}
	if err := w.Close(); err != nil {
		return nil, err
	}
	return bb.Bytes(), nil
}
