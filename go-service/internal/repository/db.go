// Package repository provides a thin database access layer.
// It wraps database/sql and works with both SQLite and MySQL.
package repository

import (
	"database/sql"
	"fmt"

	_ "github.com/go-sql-driver/mysql"
	_ "github.com/mattn/go-sqlite3"
)

// Open connects to the database identified by dbType ("sqlite"|"mysql") and dsn.
func Open(dbType, dsn string) (*sql.DB, error) {
	driver := dbType
	if dbType == "sqlite" {
		driver = "sqlite3"
		// Enable WAL mode and foreign keys
		dsn = dsn + "?_journal=WAL&_foreign_keys=on&cache=shared&_busy_timeout=5000"
	}
	db, err := sql.Open(driver, dsn)
	if err != nil {
		return nil, fmt.Errorf("open db: %w", err)
	}

	if dbType == "sqlite" {
		// SQLite performs best with a small connection pool due to write serialisation.
		db.SetMaxOpenConns(1)
		db.SetMaxIdleConns(1)
	} else {
		db.SetMaxOpenConns(25)
		db.SetMaxIdleConns(10)
	}

	if err = db.Ping(); err != nil {
		return nil, fmt.Errorf("ping db: %w", err)
	}
	return db, nil
}

// Migrate creates all tables required by the service.
func Migrate(db *sql.DB, dbType string) error {
	text := "TEXT"
	textLong := "TEXT"
	autoInc := "INTEGER PRIMARY KEY AUTOINCREMENT"
	ts := "DATETIME DEFAULT CURRENT_TIMESTAMP"
	if dbType == "mysql" {
		text = "VARCHAR(255)"
		textLong = "TEXT"
		autoInc = "INT PRIMARY KEY AUTO_INCREMENT"
		ts = "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
	}

	stmts := []string{
		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS epg_data (
			date %s NOT NULL,
			channel %s NOT NULL,
			epg_diyp %s,
			PRIMARY KEY (date, channel)
		)`, text, text, textLong),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS gen_list (
			id %s,
			channel %s NOT NULL
		)`, autoInc, text),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS update_log (
			id %s,
			timestamp %s,
			log_message %s NOT NULL
		)`, autoInc, ts, textLong),

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
		)`, text, text, text, text, textLong, text, text, text, text, text, text),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS channels_info (
			streamUrl %s PRIMARY KEY,
			resolution %s,
			speed %s
		)`, textLong, text, text),

		fmt.Sprintf(`CREATE TABLE IF NOT EXISTS access_log (
			id %s,
			access_time %s NOT NULL,
			client_ip %s NOT NULL,
			method %s NOT NULL,
			url TEXT NOT NULL,
			user_agent TEXT NOT NULL,
			access_denied INTEGER DEFAULT 0,
			deny_message TEXT
		)`, autoInc, ts, text, text),
	}

	for _, s := range stmts {
		if _, err := db.Exec(s); err != nil {
			return fmt.Errorf("migrate: %w", err)
		}
	}
	return nil
}
