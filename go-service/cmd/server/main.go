// cmd/server/main.go — IPTV Go Service entry point
//
// Start with:
//
//	go run ./cmd/server
//
// Or after building:
//
//	./iptv-service
//
// See .env.example for all supported environment variables.
package main

import (
	"context"
	"encoding/json"
	"log"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"path/filepath"
	"syscall"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/taksssss/iptv-tool/go-service/internal/epg"
	"github.com/taksssss/iptv-tool/go-service/internal/handler"
	"github.com/taksssss/iptv-tool/go-service/internal/middleware"
	"github.com/taksssss/iptv-tool/go-service/internal/repository"
	"github.com/taksssss/iptv-tool/go-service/internal/service"
	"github.com/taksssss/iptv-tool/go-service/internal/stream"
	"github.com/taksssss/iptv-tool/go-service/pkg/cache"
	"github.com/taksssss/iptv-tool/go-service/pkg/config"
	"github.com/taksssss/iptv-tool/go-service/pkg/httpclient"
	"github.com/taksssss/iptv-tool/go-service/pkg/logger"
)

func main() {
	// ── Configuration ────────────────────────────────────────────────────────
	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("config: %v", err)
	}

	// Optionally merge PHP-compatible config.json
	cfgPath := filepath.Join(cfg.DataDir, "config.json")
	if _, err := os.Stat(cfgPath); err == nil {
		if err := config.LoadFromFile(cfgPath, cfg); err != nil {
			log.Printf("warn: could not load %s: %v", cfgPath, err)
		}
	}

	// ── Logger ────────────────────────────────────────────────────────────────
	appLog := logger.New(cfg.LogLevel)
	slog.SetDefault(appLog)

	// ── Database ──────────────────────────────────────────────────────────────
	db, err := repository.Open(cfg.DBType, cfg.DBDSN)
	if err != nil {
		appLog.Error("db open", "err", err)
		os.Exit(1)
	}
	defer db.Close()

	if err := repository.Migrate(db, cfg.DBType); err != nil {
		appLog.Error("db migrate", "err", err)
		os.Exit(1)
	}

	// ── Cache ─────────────────────────────────────────────────────────────────
	var appCache cache.Cache
	switch cfg.CacheType {
	case "redis":
		rc, err := cache.NewRedis(cfg.RedisAddr, cfg.RedisPassword, cfg.RedisDB)
		if err != nil {
			appLog.Warn("redis unavailable, falling back to memory cache", "err", err)
			appCache = cache.NewMemory()
		} else {
			appLog.Info("connected to Redis", "addr", cfg.RedisAddr)
			appCache = rc
		}
	case "memory":
		appCache = cache.NewMemory()
	default:
		appCache = cache.NoopCache{}
	}

	// ── HTTP Client (shared upstream connection pool) ─────────────────────────
	httpOpts := httpclient.DefaultOptions()
	httpOpts.DisableTLSVerify = true // many IPTV sources use self-signed certs
	httpOpts.DialTimeout = time.Duration(cfg.ProxyTimeout) * time.Second
	upstreamClient := httpclient.New(httpOpts)

	// ── Domain objects ────────────────────────────────────────────────────────
	icons := loadIconList(cfg.DataDir, appLog)
	norm := epg.NewNormaliser(nil, nil, icons, cfg.DefaultIcon)

	epgRepo := repository.NewEPGRepo(db)
	channelRepo := repository.NewChannelRepo(db)

	epgSvc := service.NewEPGService(epgRepo, appCache, norm, cfg)
	channelSvc := service.NewChannelService(channelRepo)

	serverURL := cfg.ServerURL
	proxyBase := serverURL + "/proxy?url="
	streamProxy := stream.NewProxy(upstreamClient, proxyBase)

	// ── Handlers ──────────────────────────────────────────────────────────────
	epgHandler := handler.NewEPGHandler(epgSvc)
	playlistHandler := handler.NewPlaylistHandler(channelSvc, epgRepo, appCache, cfg)
	proxyHandler := handler.NewProxyHandler(streamProxy, cfg)
	iconHandler := handler.NewIconHandler(norm, cfg)

	// ── Router ────────────────────────────────────────────────────────────────
	if cfg.LogLevel != "debug" {
		gin.SetMode(gin.ReleaseMode)
	}

	r := gin.New()
	r.Use(gin.Recovery())
	r.Use(corsMiddleware())
	r.Use(middleware.Auth(cfg))

	// ── EPG routes ────────────────────────────────────────────────────────────
	r.GET("/", func(c *gin.Context) {
		switch {
		case c.Query("ch") != "" && c.Query("type") == "icon":
			iconHandler.ServeIcon(c)
		case c.Query("ch") != "":
			epgHandler.QueryDIYP(c)
		case c.Query("channel") != "":
			epgHandler.QueryLoveTV(c)
		default:
			playlistHandler.ServeEPGXML(c)
		}
	})

	r.GET("/epg.xml", playlistHandler.ServeEPGXML)
	r.GET("/epg.xml.gz", playlistHandler.ServeEPGGZ)
	r.GET("/t.xml", playlistHandler.ServeEPGXML)
	r.GET("/t.xml.gz", playlistHandler.ServeEPGGZ)

	// ── Playlist routes ───────────────────────────────────────────────────────
	r.GET("/playlist.m3u", playlistHandler.ServeM3U)
	r.GET("/playlist.txt", playlistHandler.ServeTXT)

	// ── Proxy routes ──────────────────────────────────────────────────────────
	r.GET("/proxy", proxyHandler.ServeProxy)
	r.GET("/proxy.php", proxyHandler.ServeProxy) // backward-compat alias

	// ── Health check ─────────────────────────────────────────────────────────
	r.GET("/healthz", func(c *gin.Context) {
		c.JSON(http.StatusOK, gin.H{"status": "ok", "time": time.Now().Unix()})
	})

	// ── HTTP server with graceful shutdown ───────────────────────────────────
	srv := &http.Server{
		Addr:         cfg.ListenAddr,
		Handler:      r,
		ReadTimeout:  15 * time.Second,
		WriteTimeout: 0, // disabled for streaming responses
		IdleTimeout:  120 * time.Second,
	}

	go func() {
		appLog.Info("IPTV service starting", "addr", cfg.ListenAddr)
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			appLog.Error("listen", "err", err)
			os.Exit(1)
		}
	}()

	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	appLog.Info("shutting down…")
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	if err := srv.Shutdown(ctx); err != nil {
		appLog.Error("shutdown", "err", err)
	}
	appLog.Info("stopped")
}

// corsMiddleware adds CORS headers to every response.
func corsMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Header("Access-Control-Allow-Origin", "*")
		c.Header("Access-Control-Allow-Methods", "GET, HEAD, OPTIONS")
		c.Header("Access-Control-Allow-Headers", "Origin, Content-Type, Accept")
		if c.Request.Method == http.MethodOptions {
			c.AbortWithStatus(http.StatusNoContent)
			return
		}
		c.Next()
	}
}

// loadIconList merges the user icon list with the default icon list.
// User-defined entries take precedence.
func loadIconList(dataDir string, appLog *slog.Logger) map[string]string {
	merged := make(map[string]string)

	// Load default first, then user (user overwrites default)
	for _, name := range []string{"defaultIconList.json", "iconList.json"} {
		p := filepath.Join(dataDir, name)
		data, err := os.ReadFile(filepath.Clean(p))
		if err != nil {
			continue
		}
		var m map[string]string
		if err := json.Unmarshal(data, &m); err != nil {
			appLog.Warn("icon list parse error", "file", name, "err", err)
			continue
		}
		for k, v := range m {
			merged[k] = v
		}
	}
	return merged
}

