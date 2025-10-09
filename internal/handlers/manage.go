package handlers

import (
	"encoding/json"
	"net/http"

	"github.com/taksssss/iptv-tool/internal/config"
	"github.com/taksssss/iptv-tool/internal/database"
)

// ManageHandler handles the management interface
type ManageHandler struct {
	cfg     *config.Config
	db      *database.DB
	dataDir string
}

// NewManageHandler creates a new manage handler
func NewManageHandler(cfg *config.Config, db *database.DB, dataDir string) *ManageHandler {
	return &ManageHandler{
		cfg:     cfg,
		db:      db,
		dataDir: dataDir,
	}
}

// Handle handles management requests
func (h *ManageHandler) Handle(w http.ResponseWriter, r *http.Request) {
	// TODO: Implement management interface
	// This would include:
	// - Configuration management
	// - Channel management
	// - EPG data viewing
	// - Log viewing
	// - Authentication

	if r.Method == http.MethodGet {
		// Serve management HTML interface
		http.ServeFile(w, r, "epg/assets/html/manage.html")
		return
	}

	if r.Method == http.MethodPost {
		// Handle configuration updates and other POST requests
		action := r.URL.Query().Get("action")

		switch action {
		case "save_config":
			h.handleSaveConfig(w, r)
		case "get_config":
			h.handleGetConfig(w, r)
		default:
			http.Error(w, "Unknown action", http.StatusBadRequest)
		}
		return
	}

	http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
}

// handleSaveConfig saves configuration
func (h *ManageHandler) handleSaveConfig(w http.ResponseWriter, r *http.Request) {
	// Parse form data
	if err := r.ParseForm(); err != nil {
		http.Error(w, "Failed to parse form", http.StatusBadRequest)
		return
	}

	// TODO: Update configuration from form data
	// Save to config.json

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

// handleGetConfig returns current configuration
func (h *ManageHandler) handleGetConfig(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(h.cfg)
}
