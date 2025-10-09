package database

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/taksssss/iptv-tool/internal/config"
)

func TestInitializeSQLite(t *testing.T) {
	// Create temporary directory
	tmpDir, err := os.MkdirTemp("", "iptv-tool-test")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}
	defer os.RemoveAll(tmpDir)

	// Create config
	cfg := &config.Config{
		DataDir: tmpDir,
		DBType:  "sqlite",
	}

	// Initialize database
	db, err := Initialize(cfg)
	if err != nil {
		t.Fatalf("Failed to initialize database: %v", err)
	}
	defer db.Close()

	// Verify database file was created
	dbPath := filepath.Join(tmpDir, "data.db")
	if _, err := os.Stat(dbPath); os.IsNotExist(err) {
		t.Error("Database file was not created")
	}

	// Verify tables were created
	tables := []string{
		"epg_data",
		"gen_list",
		"update_log",
		"cron_log",
		"channels",
		"channels_info",
		"access_log",
	}

	for _, table := range tables {
		var count int
		query := "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name=?"
		err := db.QueryRow(query, table).Scan(&count)
		if err != nil {
			t.Errorf("Failed to check table %s: %v", table, err)
		}
		if count != 1 {
			t.Errorf("Table %s was not created", table)
		}
	}
}

func TestDatabaseOperations(t *testing.T) {
	// Create temporary directory
	tmpDir, err := os.MkdirTemp("", "iptv-tool-test")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}
	defer os.RemoveAll(tmpDir)

	// Create config
	cfg := &config.Config{
		DataDir: tmpDir,
		DBType:  "sqlite",
	}

	// Initialize database
	db, err := Initialize(cfg)
	if err != nil {
		t.Fatalf("Failed to initialize database: %v", err)
	}
	defer db.Close()

	// Test insert
	_, err = db.Exec(`INSERT INTO gen_list (channel) VALUES (?)`, "CCTV1")
	if err != nil {
		t.Fatalf("Failed to insert data: %v", err)
	}

	// Test query
	var channel string
	err = db.QueryRow("SELECT channel FROM gen_list WHERE channel = ?", "CCTV1").Scan(&channel)
	if err != nil {
		t.Fatalf("Failed to query data: %v", err)
	}

	if channel != "CCTV1" {
		t.Errorf("Expected channel to be 'CCTV1', got '%s'", channel)
	}
}
