package server

import (
	"net/http"
	"time"

	"github.com/taksssss/iptv-tool/internal/config"
	"github.com/taksssss/iptv-tool/internal/database"
	"github.com/taksssss/iptv-tool/internal/handlers"
)

// Server represents the HTTP server
type Server struct {
	cfg     *config.Config
	db      *database.DB
	dataDir string
}

// New creates a new server instance
func New(cfg *config.Config, db *database.DB, dataDir string) *Server {
	return &Server{
		cfg:     cfg,
		db:      db,
		dataDir: dataDir,
	}
}

// Start starts the HTTP server
func (s *Server) Start(port string) error {
	mux := http.NewServeMux()

	// Create handlers
	indexHandler := handlers.NewIndexHandler(s.cfg, s.db, s.dataDir)
	manageHandler := handlers.NewManageHandler(s.cfg, s.db, s.dataDir)
	updateHandler := handlers.NewUpdateHandler(s.cfg, s.db, s.dataDir)
	checkHandler := handlers.NewCheckHandler(s.cfg, s.db, s.dataDir)
	proxyHandler := handlers.NewProxyHandler(s.cfg, s.db)

	// Register routes
	mux.HandleFunc("/", indexHandler.Handle)
	mux.HandleFunc("/index.php", indexHandler.Handle)
	mux.HandleFunc("/manage.php", manageHandler.Handle)
	mux.HandleFunc("/update.php", updateHandler.Handle)
	mux.HandleFunc("/check.php", checkHandler.Handle)
	mux.HandleFunc("/proxy.php", proxyHandler.Handle)

	// Static file serving
	assetsDir := "./epg/assets"
	mux.Handle("/assets/", http.StripPrefix("/assets/", http.FileServer(http.Dir(assetsDir))))
	
	dataFilesDir := s.dataDir
	mux.Handle("/data/", http.StripPrefix("/data/", http.FileServer(http.Dir(dataFilesDir))))

	// Create HTTP server with timeouts
	srv := &http.Server{
		Addr:         ":" + port,
		Handler:      mux,
		ReadTimeout:  30 * time.Second,
		WriteTimeout: 30 * time.Second,
		IdleTimeout:  60 * time.Second,
	}

	return srv.ListenAndServe()
}
