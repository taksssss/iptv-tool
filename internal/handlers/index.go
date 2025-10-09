package handlers

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"
	"strings"
	"time"

	"github.com/taksssss/iptv-tool/internal/config"
	"github.com/taksssss/iptv-tool/internal/database"
	"github.com/taksssss/iptv-tool/internal/utils"
)

// IndexHandler handles the main EPG requests
type IndexHandler struct {
	cfg     *config.Config
	db      *database.DB
	dataDir string
}

// NewIndexHandler creates a new index handler
func NewIndexHandler(cfg *config.Config, db *database.DB, dataDir string) *IndexHandler {
	return &IndexHandler{
		cfg:     cfg,
		db:      db,
		dataDir: dataDir,
	}
}

// Handle handles the index requests
func (h *IndexHandler) Handle(w http.ResponseWriter, r *http.Request) {
	// Parse query parameters
	query := r.URL.Query()

	// Check for access control
	if !h.checkAccess(w, r) {
		return
	}

	// Handle different request types
	if query.Get("live") == "1" {
		h.handleLiveRequest(w, r, query)
		return
	}

	// Handle EPG requests
	channel := query.Get("ch")
	if channel == "" {
		channel = query.Get("channel")
	}

	// Handle icon requests
	if query.Get("type") == "icon" {
		h.handleIconRequest(w, r, channel)
		return
	}

	// Handle XMLTV requests
	if channel == "" {
		h.handleXMLTVRequest(w, r)
		return
	}

	// Handle channel EPG requests (DIYP/百川 or 超级直播 format)
	h.handleChannelEPG(w, r, channel, query.Get("date"))
}

// checkAccess checks if the request is allowed based on token, user agent, and IP
func (h *IndexHandler) checkAccess(w http.ResponseWriter, r *http.Request) bool {
	// Token check
	if h.cfg.Token != "" && h.cfg.TokenRange == 1 {
		token := r.URL.Query().Get("token")
		if token != h.cfg.Token {
			http.Error(w, "Forbidden: Invalid token", http.StatusForbidden)
			h.logAccess(r, true, "Invalid token")
			return false
		}
	}

	// User-Agent check
	if h.cfg.UserAgent != "" && h.cfg.UserAgentRange == 0 {
		ua := r.Header.Get("User-Agent")
		if ua != h.cfg.UserAgent {
			http.Error(w, "Forbidden: Invalid User-Agent", http.StatusForbidden)
			h.logAccess(r, true, "Invalid User-Agent")
			return false
		}
	}

	// IP blacklist/whitelist check
	clientIP := utils.GetClientIP(r)
	if h.cfg.IPListMode != 0 {
		ipList := utils.LoadIPList(h.dataDir, h.cfg.IPListMode)
		if h.cfg.IPListMode == 1 { // Blacklist
			if utils.IPInList(clientIP, ipList) {
				http.Error(w, "Forbidden: IP in blacklist", http.StatusForbidden)
				h.logAccess(r, true, "IP in blacklist")
				return false
			}
		} else if h.cfg.IPListMode == 2 { // Whitelist
			if !utils.IPInList(clientIP, ipList) {
				http.Error(w, "Forbidden: IP not in whitelist", http.StatusForbidden)
				h.logAccess(r, true, "IP not in whitelist")
				return false
			}
		}
	}

	h.logAccess(r, false, "")
	return true
}

// logAccess logs the access to the database
func (h *IndexHandler) logAccess(r *http.Request, denied bool, message string) {
	clientIP := utils.GetClientIP(r)
	method := r.Method
	url := r.URL.String()
	userAgent := r.Header.Get("User-Agent")
	
	deniedInt := 0
	if denied {
		deniedInt = 1
	}

	_, _ = h.db.Exec(`INSERT INTO access_log 
		(access_time, client_ip, method, url, user_agent, access_denied, deny_message) 
		VALUES (?, ?, ?, ?, ?, ?, ?)`,
		time.Now().Format("2006-01-02 15:04:05"), clientIP, method, url, userAgent, deniedInt, message)
}

// handleChannelEPG handles EPG requests for a specific channel
func (h *IndexHandler) handleChannelEPG(w http.ResponseWriter, r *http.Request, channel, date string) {
	if date == "" {
		date = time.Now().Format("2006-01-02")
	}

	// Clean channel name
	cleanedChannel := utils.CleanChannelName(channel, h.cfg.ChtToChs == 1, h.cfg)

	// Query EPG data
	var epgDIYP string
	err := h.db.QueryRow("SELECT epg_diyp FROM epg_data WHERE date = ? AND channel = ?", 
		date, cleanedChannel).Scan(&epgDIYP)

	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Content-Type", "application/json")

	if err == sql.ErrNoRows {
		// Return empty result or default
		if h.cfg.RetDefault == 1 {
			json.NewEncoder(w).Encode(map[string]interface{}{
				"channel_name": channel,
				"date":         date,
				"epg_data":     []interface{}{},
			})
		} else {
			http.Error(w, "No EPG data found", http.StatusNotFound)
		}
		return
	} else if err != nil {
		http.Error(w, "Database error", http.StatusInternalServerError)
		return
	}

	// Parse and return EPG data
	var epgData []interface{}
	if err := json.Unmarshal([]byte(epgDIYP), &epgData); err != nil {
		http.Error(w, "Failed to parse EPG data", http.StatusInternalServerError)
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"channel_name": channel,
		"date":         date,
		"epg_data":     epgData,
	})
}

// handleXMLTVRequest handles XMLTV format requests
func (h *IndexHandler) handleXMLTVRequest(w http.ResponseWriter, r *http.Request) {
	// Check if XML file exists
	xmlPath := h.dataDir + "/t.xml"
	xmlGzPath := h.dataDir + "/t.xml.gz"

	// Try gzipped version first
	if utils.FileExists(xmlGzPath) {
		http.ServeFile(w, r, xmlGzPath)
		return
	}

	// Try regular XML
	if utils.FileExists(xmlPath) {
		http.ServeFile(w, r, xmlPath)
		return
	}

	// Redirect to XML file or return 404
	http.Error(w, "XMLTV file not found", http.StatusNotFound)
}

// handleIconRequest handles icon/logo requests
func (h *IndexHandler) handleIconRequest(w http.ResponseWriter, r *http.Request, channel string) {
	if channel == "" {
		http.Error(w, "Channel name required", http.StatusBadRequest)
		return
	}

	// Load icon list
	iconList := utils.LoadIconList(h.dataDir)

	// Match icon
	iconURL := utils.IconURLMatch(channel, iconList, h.cfg.DefaultIcon)

	w.Header().Set("Access-Control-Allow-Origin", "*")
	w.Header().Set("Content-Type", "application/json")

	if iconURL == "" {
		json.NewEncoder(w).Encode(map[string]string{
			"channel": channel,
			"icon":    "",
		})
	} else {
		json.NewEncoder(w).Encode(map[string]string{
			"channel": channel,
			"icon":    iconURL,
		})
	}
}

// handleLiveRequest handles live source requests
func (h *IndexHandler) handleLiveRequest(w http.ResponseWriter, r *http.Request, query map[string][]string) {
	w.Header().Set("Content-Type", "text/plain")

	url := ""
	if vals, ok := query["url"]; ok && len(vals) > 0 {
		url = vals[0]
	}
	if url == "" {
		url = "default"
	}

	fileType := "m3u"
	if vals, ok := query["type"]; ok && len(vals) > 0 {
		fileType = vals[0]
	}

	// Calculate file path
	filePath := fmt.Sprintf("%s/live/file/%s.%s", h.dataDir, utils.MD5Hash(url), fileType)

	latest := ""
	if vals, ok := query["latest"]; ok && len(vals) > 0 {
		latest = vals[0]
	}

	// Check if file exists or needs to be regenerated
	if !utils.FileExists(filePath) || latest == "1" {
		// Parse source info to generate file
		if err := utils.ParseSourceInfo(url, h.cfg, h.db, h.dataDir); err != nil {
			http.Error(w, "File not found", http.StatusNotFound)
			return
		}
	}

	// Read and process file
	content, err := utils.ReadFile(filePath)
	if err != nil {
		http.Error(w, "File not found", http.StatusNotFound)
		return
	}

	// Replace TVG URL if needed
	if strings.Contains(content, "tvg-url=") && h.cfg.Token != "" {
		serverURL := utils.GetServerURL(r)
		content = strings.ReplaceAll(content, `tvg-url="`, fmt.Sprintf(`tvg-url="%s/index.php?token=%s&`, serverURL, h.cfg.Token))
	}

	w.Write([]byte(content))
}
