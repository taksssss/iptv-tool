package handlers

import (
	"net/http"

	"github.com/taksssss/iptv-tool/internal/config"
	"github.com/taksssss/iptv-tool/internal/database"
)

// UpdateHandler handles EPG data updates
type UpdateHandler struct {
	cfg     *config.Config
	db      *database.DB
	dataDir string
}

// NewUpdateHandler creates a new update handler
func NewUpdateHandler(cfg *config.Config, db *database.DB, dataDir string) *UpdateHandler {
	return &UpdateHandler{
		cfg:     cfg,
		db:      db,
		dataDir: dataDir,
	}
}

// Handle handles update requests
func (h *UpdateHandler) Handle(w http.ResponseWriter, r *http.Request) {
	// Check if it's an AJAX request or CLI
	if r.Header.Get("X-Requested-With") != "XMLHttpRequest" {
		http.Error(w, "Forbidden: Direct access not allowed", http.StatusForbidden)
		return
	}

	// TODO: Implement EPG update logic
	// This would include:
	// - Fetching EPG data from configured sources
	// - Parsing and storing in database
	// - Generating XML files if configured
	// - Logging progress

	w.Header().Set("Content-Type", "text/html")
	w.Write([]byte("Update completed"))
}
