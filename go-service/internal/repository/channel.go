package repository

import (
	"context"
	"database/sql"

	"github.com/taksssss/iptv-tool/go-service/internal/model"
)

// ChannelRepo handles channel table access.
type ChannelRepo struct {
	db *sql.DB
}

// NewChannelRepo constructs a ChannelRepo.
func NewChannelRepo(db *sql.DB) *ChannelRepo {
	return &ChannelRepo{db: db}
}

// ListActive returns all enabled channels for a given live_source_config.
func (r *ChannelRepo) ListActive(ctx context.Context, cfg string) ([]*model.Channel, error) {
	rows, err := r.db.QueryContext(ctx, `
		SELECT groupPrefix, groupTitle, channelName, chsChannelName,
		       streamUrl, iconUrl, tvgId, tvgName, disable, modified, source, tag, config
		FROM channels
		WHERE disable = 0 AND config = ?
		ORDER BY groupPrefix, groupTitle, channelName`, cfg)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	var out []*model.Channel
	for rows.Next() {
		c := &model.Channel{}
		if err := rows.Scan(
			&c.GroupPrefix, &c.GroupTitle, &c.ChannelName, &c.ChsChannelName,
			&c.StreamURL, &c.IconURL, &c.TvgID, &c.TvgName,
			&c.Disable, &c.Modified, &c.Source, &c.Tag, &c.Config,
		); err != nil {
			return nil, err
		}
		out = append(out, c)
	}
	return out, rows.Err()
}

// FindByStreamURL returns channel speed info.
func (r *ChannelRepo) FindByStreamURL(ctx context.Context, url string) (*model.ChannelInfo, error) {
	row := r.db.QueryRowContext(ctx,
		"SELECT streamUrl, resolution, speed FROM channels_info WHERE streamUrl = ?", url)
	ci := &model.ChannelInfo{}
	if err := row.Scan(&ci.StreamURL, &ci.Resolution, &ci.Speed); err != nil {
		if err == sql.ErrNoRows {
			return nil, nil
		}
		return nil, err
	}
	return ci, nil
}
