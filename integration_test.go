package main

import (
	"io"
	"net/http"
	"os"
	"path/filepath"
	"testing"
	"time"

	"github.com/taksssss/iptv-tool/internal/config"
	"github.com/taksssss/iptv-tool/internal/database"
	"github.com/taksssss/iptv-tool/internal/server"
)

func TestServerIntegration(t *testing.T) {
	// Create temporary directory
	tmpDir, err := os.MkdirTemp("", "iptv-tool-integration")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}
	defer os.RemoveAll(tmpDir)

	// Setup directories
	dirs := []string{
		tmpDir + "/icon",
		tmpDir + "/live",
		tmpDir + "/live/file",
	}
	for _, dir := range dirs {
		if err := os.MkdirAll(dir, 0755); err != nil {
			t.Fatalf("Failed to create directory %s: %v", dir, err)
		}
	}

	// Initialize configuration
	cfg, err := config.Load(tmpDir)
	if err != nil {
		t.Fatalf("Failed to load config: %v", err)
	}

	// Initialize database
	db, err := database.Initialize(cfg)
	if err != nil {
		t.Fatalf("Failed to initialize database: %v", err)
	}
	defer db.Close()

	// Create server
	srv := server.New(cfg, db, tmpDir)

	// Start server in a goroutine
	go func() {
		if err := srv.Start("18080"); err != nil {
			t.Logf("Server error: %v", err)
		}
	}()

	// Give server time to start
	time.Sleep(500 * time.Millisecond)

	// Test health check by accessing root path
	resp, err := http.Get("http://localhost:18080/")
	if err != nil {
		t.Fatalf("Failed to make request: %v", err)
	}
	defer resp.Body.Close()

	// We expect either 200 or 404 (since XMLTV file doesn't exist yet)
	if resp.StatusCode != http.StatusOK && resp.StatusCode != http.StatusNotFound {
		body, _ := io.ReadAll(resp.Body)
		t.Errorf("Unexpected status code: %d, body: %s", resp.StatusCode, string(body))
	}

	// Test manage.php endpoint
	resp2, err := http.Get("http://localhost:18080/manage.php")
	if err != nil {
		t.Fatalf("Failed to make request to manage: %v", err)
	}
	defer resp2.Body.Close()

	// We expect either 200 or 404 (if HTML file doesn't exist in test environment)
	if resp2.StatusCode != http.StatusOK && resp2.StatusCode != http.StatusNotFound {
		t.Errorf("Unexpected status code for manage: %d", resp2.StatusCode)
	}

	t.Log("Server integration test passed!")
}

func TestDatabasePersistence(t *testing.T) {
	// Create temporary directory
	tmpDir, err := os.MkdirTemp("", "iptv-tool-db-persist")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}
	defer os.RemoveAll(tmpDir)

	// Initialize configuration
	cfg, err := config.Load(tmpDir)
	if err != nil {
		t.Fatalf("Failed to load config: %v", err)
	}

	// Initialize database first time
	db1, err := database.Initialize(cfg)
	if err != nil {
		t.Fatalf("Failed to initialize database: %v", err)
	}

	// Insert test data
	_, err = db1.Exec(`INSERT INTO gen_list (channel) VALUES (?)`, "TEST_CHANNEL")
	if err != nil {
		t.Fatalf("Failed to insert data: %v", err)
	}

	db1.Close()

	// Re-open database
	db2, err := database.Initialize(cfg)
	if err != nil {
		t.Fatalf("Failed to re-open database: %v", err)
	}
	defer db2.Close()

	// Verify data persists
	var channel string
	err = db2.QueryRow("SELECT channel FROM gen_list WHERE channel = ?", "TEST_CHANNEL").Scan(&channel)
	if err != nil {
		t.Fatalf("Failed to query persisted data: %v", err)
	}

	if channel != "TEST_CHANNEL" {
		t.Errorf("Expected channel 'TEST_CHANNEL', got '%s'", channel)
	}

	// Verify database file exists
	dbPath := filepath.Join(tmpDir, "data.db")
	if _, err := os.Stat(dbPath); os.IsNotExist(err) {
		t.Error("Database file does not exist")
	}
}
