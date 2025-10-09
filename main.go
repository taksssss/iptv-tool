package main

import (
	"flag"
	"fmt"
	"log"
	"os"

	"github.com/taksssss/iptv-tool/internal/config"
	"github.com/taksssss/iptv-tool/internal/cron"
	"github.com/taksssss/iptv-tool/internal/database"
	"github.com/taksssss/iptv-tool/internal/server"
)

func main() {
	// Command line flags
	dataDir := flag.String("data", "./epg/data", "Data directory path")
	port := flag.String("port", "80", "Server port")
	flag.Parse()

	// Initialize configuration
	cfg, err := config.Load(*dataDir)
	if err != nil {
		log.Fatalf("Failed to load configuration: %v", err)
	}

	// Initialize database
	db, err := database.Initialize(cfg)
	if err != nil {
		log.Fatalf("Failed to initialize database: %v", err)
	}
	defer db.Close()

	// Set up icon directories and other data directories
	if err := setupDirectories(*dataDir); err != nil {
		log.Fatalf("Failed to setup directories: %v", err)
	}

	// Start cron service if configured
	cronService := cron.NewService(cfg, db)
	if err := cronService.Start(); err != nil {
		log.Printf("Warning: Failed to start cron service: %v", err)
	}

	// Start HTTP server
	srv := server.New(cfg, db, *dataDir)
	log.Printf("Starting IPTV Tool server on port %s...", *port)
	if err := srv.Start(*port); err != nil {
		log.Fatalf("Server error: %v", err)
	}
}

func setupDirectories(dataDir string) error {
	dirs := []string{
		dataDir,
		dataDir + "/icon",
		dataDir + "/live",
		dataDir + "/live/file",
	}

	for _, dir := range dirs {
		if err := os.MkdirAll(dir, 0755); err != nil {
			return fmt.Errorf("failed to create directory %s: %w", dir, err)
		}
	}

	return nil
}
