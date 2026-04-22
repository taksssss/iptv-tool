package middleware_test

import (
	"net/http"
	"net/http/httptest"
	"testing"

	"github.com/gin-gonic/gin"
	"github.com/taksssss/iptv-tool/go-service/internal/middleware"
	"github.com/taksssss/iptv-tool/go-service/pkg/config"
)

func init() {
	gin.SetMode(gin.TestMode)
}

func newRouter(cfg *config.Config) *gin.Engine {
	r := gin.New()
	r.Use(middleware.Auth(cfg))
	r.GET("/epg.xml", func(c *gin.Context) { c.String(http.StatusOK, "ok") })
	r.GET("/playlist.m3u", func(c *gin.Context) { c.String(http.StatusOK, "ok") })
	return r
}

func TestAuth_NoTokenRequired(t *testing.T) {
	cfg := &config.Config{TokenRange: 0}
	r := newRouter(cfg)

	req := httptest.NewRequest(http.MethodGet, "/epg.xml", nil)
	w := httptest.NewRecorder()
	r.ServeHTTP(w, req)

	if w.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", w.Code)
	}
}

func TestAuth_ValidToken(t *testing.T) {
	cfg := &config.Config{
		TokenRange: 3, // all endpoints require auth
		Tokens:     []string{"secret"},
	}
	r := newRouter(cfg)

	req := httptest.NewRequest(http.MethodGet, "/epg.xml?token=secret", nil)
	w := httptest.NewRecorder()
	r.ServeHTTP(w, req)

	if w.Code != http.StatusOK {
		t.Errorf("expected 200, got %d", w.Code)
	}
}

func TestAuth_InvalidToken(t *testing.T) {
	cfg := &config.Config{
		TokenRange: 3,
		Tokens:     []string{"secret"},
	}
	r := newRouter(cfg)

	req := httptest.NewRequest(http.MethodGet, "/epg.xml?token=wrong", nil)
	w := httptest.NewRecorder()
	r.ServeHTTP(w, req)

	if w.Code != http.StatusForbidden {
		t.Errorf("expected 403, got %d", w.Code)
	}
}

func TestAuth_EPGOnlyRange_LiveFree(t *testing.T) {
	// TokenRange=1: EPG requires token, live is free
	cfg := &config.Config{
		TokenRange: 1,
		Tokens:     []string{"secret"},
	}
	r := newRouter(cfg)

	// Live endpoint without token → should pass
	req := httptest.NewRequest(http.MethodGet, "/playlist.m3u", nil)
	w := httptest.NewRecorder()
	r.ServeHTTP(w, req)

	if w.Code != http.StatusOK {
		t.Errorf("expected 200 for live endpoint without token, got %d", w.Code)
	}
}
