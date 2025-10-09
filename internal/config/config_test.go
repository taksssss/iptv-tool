package config

import (
	"os"
	"path/filepath"
	"testing"
)

func TestLoadDefaultConfig(t *testing.T) {
	// Create temporary directory
	tmpDir, err := os.MkdirTemp("", "iptv-tool-test")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}
	defer os.RemoveAll(tmpDir)

	// Load config (should create default)
	cfg, err := Load(tmpDir)
	if err != nil {
		t.Fatalf("Failed to load config: %v", err)
	}

	// Check default values
	if cfg.DBType != "sqlite" {
		t.Errorf("Expected DBType to be 'sqlite', got '%s'", cfg.DBType)
	}

	if cfg.DaysToKeep != 7 {
		t.Errorf("Expected DaysToKeep to be 7, got %d", cfg.DaysToKeep)
	}

	// Verify config file was created
	configPath := filepath.Join(tmpDir, "config.json")
	if _, err := os.Stat(configPath); os.IsNotExist(err) {
		t.Error("Config file was not created")
	}
}

func TestSaveConfig(t *testing.T) {
	// Create temporary directory
	tmpDir, err := os.MkdirTemp("", "iptv-tool-test")
	if err != nil {
		t.Fatalf("Failed to create temp dir: %v", err)
	}
	defer os.RemoveAll(tmpDir)

	// Create and save config
	cfg := getDefaultConfig()
	cfg.Token = "test-token"
	
	configPath := filepath.Join(tmpDir, "config.json")
	if err := cfg.Save(configPath); err != nil {
		t.Fatalf("Failed to save config: %v", err)
	}

	// Load it back
	loadedCfg, err := Load(tmpDir)
	if err != nil {
		t.Fatalf("Failed to load config: %v", err)
	}

	if loadedCfg.Token != "test-token" {
		t.Errorf("Expected Token to be 'test-token', got '%s'", loadedCfg.Token)
	}
}
