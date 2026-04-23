package repository

import (
	"context"
	"database/sql"
	"encoding/json"
	"fmt"

	"github.com/taksssss/iptv-tool/go-service/internal/model"
)

// EPGRepo handles all EPG data access.
type EPGRepo struct {
	db *sql.DB
}

// NewEPGRepo constructs a new EPGRepo.
func NewEPGRepo(db *sql.DB) *EPGRepo {
	return &EPGRepo{db: db}
}

// FindByDateAndChannel executes the priority-ordered fuzzy channel match and
// returns the raw epg_diyp JSON.  It mirrors the PHP query:
//
//	exact match > forward prefix match > reverse containment match
func (r *EPGRepo) FindByDateAndChannel(ctx context.Context, date, channel string) (json.RawMessage, error) {
	query := `
		SELECT epg_diyp
		FROM epg_data
		WHERE (
			channel = ?
			OR channel LIKE ?
			OR INSTR(?, channel) > 0
		)
		AND date = ?
		ORDER BY
			CASE
				WHEN channel = ? THEN 1
				WHEN channel LIKE ? THEN 2
				ELSE 3
			END,
			CASE
				WHEN channel = ? THEN NULL
				WHEN channel LIKE ? THEN LENGTH(channel)
				ELSE -LENGTH(channel)
			END
		LIMIT 1`

	like := channel + "%"
	row := r.db.QueryRowContext(ctx, query, channel, like, channel, date, channel, like, channel, like)

	var raw json.RawMessage
	if err := row.Scan(&raw); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, fmt.Errorf("epg find: %w", err)
	}
	return raw, nil
}

// ListChannels returns all channel names present in epg_data (distinct).
func (r *EPGRepo) ListChannels(ctx context.Context) ([]string, error) {
	rows, err := r.db.QueryContext(ctx, "SELECT DISTINCT channel FROM epg_data")
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var out []string
	for rows.Next() {
		var ch string
		if err := rows.Scan(&ch); err != nil {
			return nil, err
		}
		out = append(out, ch)
	}
	return out, rows.Err()
}

// ListGenChannels returns the channels in gen_list.
func (r *EPGRepo) ListGenChannels(ctx context.Context) ([]string, error) {
	rows, err := r.db.QueryContext(ctx, "SELECT channel FROM gen_list")
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var out []string
	for rows.Next() {
		var ch string
		if err := rows.Scan(&ch); err != nil {
			return nil, err
		}
		out = append(out, ch)
	}
	return out, rows.Err()
}

// AllForDate returns all EPG rows for a given date, used by XMLTV generation.
func (r *EPGRepo) AllForDate(ctx context.Context, date string) ([]*model.EPGData, error) {
	rows, err := r.db.QueryContext(ctx,
		"SELECT channel, epg_diyp FROM epg_data WHERE date = ?", date)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var out []*model.EPGData
	for rows.Next() {
		var channel string
		var raw json.RawMessage
		if err := rows.Scan(&channel, &raw); err != nil {
			return nil, err
		}
		var d model.EPGData
		if err := json.Unmarshal(raw, &d); err != nil {
			continue
		}
		out = append(out, &d)
	}
	return out, rows.Err()
}

// LogAccess inserts a row into access_log.
func (r *EPGRepo) LogAccess(ctx context.Context, accessTime, clientIP, method, url, ua string, denied int, denyMsg string) error {
	_, err := r.db.ExecContext(ctx,
		`INSERT INTO access_log (access_time, client_ip, method, url, user_agent, access_denied, deny_message)
		 VALUES (?, ?, ?, ?, ?, ?, ?)`,
		accessTime, clientIP, method, url, ua, denied, denyMsg)
	return err
}
