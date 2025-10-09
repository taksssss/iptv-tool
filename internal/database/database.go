package database

import (
	"database/sql"
	"fmt"

	_ "github.com/go-sql-driver/mysql"
	_ "github.com/mattn/go-sqlite3"
	"github.com/taksssss/iptv-tool/internal/config"
)

// DB wraps the database connection
type DB struct {
	*sql.DB
	IsSQLite bool
}

// Initialize initializes the database connection
func Initialize(cfg *config.Config) (*DB, error) {
	var (
		db       *sql.DB
		err      error
		isSQLite bool
	)

	if cfg.DBType == "sqlite" {
		isSQLite = true
		dbPath := cfg.DataDir + "/data.db"
		db, err = sql.Open("sqlite3", dbPath)
		if err != nil {
			return nil, fmt.Errorf("failed to open SQLite database: %w", err)
		}
	} else {
		dsn := fmt.Sprintf("%s:%s@tcp(%s)/%s?charset=utf8mb4&parseTime=True&loc=Local",
			cfg.MySQL.Username,
			cfg.MySQL.Password,
			cfg.MySQL.Host,
			cfg.MySQL.DBName,
		)
		db, err = sql.Open("mysql", dsn)
		if err != nil {
			return nil, fmt.Errorf("failed to open MySQL database: %w", err)
		}
	}

	if err := db.Ping(); err != nil {
		return nil, fmt.Errorf("failed to ping database: %w", err)
	}

	dbWrapper := &DB{DB: db, IsSQLite: isSQLite}

	// Initialize tables
	if err := dbWrapper.initTables(); err != nil {
		return nil, fmt.Errorf("failed to initialize tables: %w", err)
	}

	return dbWrapper, nil
}

// initTables creates the required database tables
func (db *DB) initTables() error {
	var (
		typeText     string
		typeTextLong string
		typeIntAuto  string
		typeTime     string
	)

	if db.IsSQLite {
		typeText = "TEXT"
		typeTextLong = "TEXT"
		typeIntAuto = "INTEGER PRIMARY KEY AUTOINCREMENT"
		typeTime = "DATETIME DEFAULT CURRENT_TIMESTAMP"
	} else {
		typeText = "VARCHAR(255)"
		typeTextLong = "VARCHAR(1024)"
		typeIntAuto = "INT PRIMARY KEY AUTO_INCREMENT"
		typeTime = "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
	}

	tables := []string{
		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS epg_data (
			date %s NOT NULL,
			channel %s NOT NULL,
			epg_diyp %s,
			PRIMARY KEY (date, channel)
		)`, typeText, typeText, typeTextLong),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS gen_list (
			id %s,
			channel %s NOT NULL
		)`, typeIntAuto, typeText),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS update_log (
			id %s,
			timestamp %s,
			log_message %s NOT NULL
		)`, typeIntAuto, typeTime, typeText),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS cron_log (
			id %s,
			timestamp %s,
			log_message %s NOT NULL
		)`, typeIntAuto, typeTime, typeText),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS channels (
			groupPrefix %s,
			groupTitle %s,
			channelName %s,
			chsChannelName %s,
			streamUrl %s,
			iconUrl %s,
			tvgId %s,
			tvgName %s,
			disable INTEGER DEFAULT 0,
			modified INTEGER DEFAULT 0,
			source %s,
			tag %s,
			config %s
		)`, typeText, typeText, typeText, typeText, typeTextLong, typeText, typeText, typeText, typeText, typeText, typeText),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS channels_info (
			streamUrl %s PRIMARY KEY,
			resolution %s,
			speed %s
		)`, typeTextLong, typeText, typeText),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS access_log (
			id %s,
			access_time %s NOT NULL,
			client_ip %s NOT NULL,
			method %s NOT NULL,
			url TEXT NOT NULL,
			user_agent TEXT NOT NULL,
			access_denied INTEGER DEFAULT 0,
			deny_message TEXT
		)`, typeIntAuto, typeTime, typeText, typeText),
	}

	for _, query := range tables {
		if _, err := db.Exec(query); err != nil {
			return fmt.Errorf("failed to create table: %w", err)
		}
	}

	return nil
}
