package service

import (
	"context"
	"encoding/json"
	"fmt"
	"strings"
	"time"

	"github.com/taksssss/iptv-tool/go-service/internal/epg"
	"github.com/taksssss/iptv-tool/go-service/internal/model"
	"github.com/taksssss/iptv-tool/go-service/internal/repository"
	"github.com/taksssss/iptv-tool/go-service/pkg/cache"
	"github.com/taksssss/iptv-tool/go-service/pkg/config"
)

// EPGService handles EPG business logic.
type EPGService struct {
	repo       *repository.EPGRepo
	cache      cache.Cache
	norm       *epg.Normaliser
	cfg        *config.Config
	cacheTTL   time.Duration
	serverURL  string
}

// NewEPGService constructs a new EPGService.
func NewEPGService(
	repo *repository.EPGRepo,
	c cache.Cache,
	norm *epg.Normaliser,
	cfg *config.Config,
) *EPGService {
	return &EPGService{
		repo:      repo,
		cache:     c,
		norm:      norm,
		cfg:       cfg,
		cacheTTL:  time.Duration(cfg.CacheTTL) * time.Second,
		serverURL: cfg.ServerURL,
	}
}

// QueryDIYP returns the DIYP/百川 EPG response for a channel and date.
func (s *EPGService) QueryDIYP(ctx context.Context, oriChannel, date string) (json.RawMessage, error) {
	cleanCh := s.norm.Clean(oriChannel)
	cacheKey := fmt.Sprintf("diyp:%s:%s", date, cleanCh)

	// Try cache first
	if hit, ok := s.cache.Get(ctx, cacheKey); ok {
		return json.RawMessage(s.replaceIconBase(hit)), nil
	}

	raw, err := s.repo.FindByDateAndChannel(ctx, date, cleanCh)
	if err != nil {
		return nil, err
	}

	var result json.RawMessage
	if raw == nil {
		// Return default data
		result = s.defaultDIYP(cleanCh, date, oriChannel)
	} else {
		result, err = s.enrichDIYP(raw, oriChannel, cleanCh)
		if err != nil {
			return nil, err
		}
		// Store in cache
		_ = s.cache.Set(ctx, cacheKey, string(result), s.cacheTTL)
	}

	return json.RawMessage(s.replaceIconBase(string(result))), nil
}

// QueryLoveTV returns 超级直播 format for a channel and date.
func (s *EPGService) QueryLoveTV(ctx context.Context, oriChannel, date string) (json.RawMessage, error) {
	cleanCh := s.norm.Clean(oriChannel)
	cacheKey := fmt.Sprintf("lovetv:%s:%s", date, cleanCh)

	if hit, ok := s.cache.Get(ctx, cacheKey); ok {
		return json.RawMessage(s.replaceIconBase(hit)), nil
	}

	raw, err := s.repo.FindByDateAndChannel(ctx, date, cleanCh)
	if err != nil {
		return nil, err
	}

	var result json.RawMessage
	if raw == nil {
		result = s.defaultLoveTV(cleanCh, oriChannel)
	} else {
		result, err = s.convertToLoveTV(raw, oriChannel, cleanCh, date)
		if err != nil {
			return nil, err
		}
		_ = s.cache.Set(ctx, cacheKey, string(result), s.cacheTTL)
	}

	return json.RawMessage(s.replaceIconBase(string(result))), nil
}

// enrichDIYP decodes the raw DIYP blob, removes the source field, and injects an icon URL.
func (s *EPGService) enrichDIYP(raw json.RawMessage, oriChannel, cleanCh string) (json.RawMessage, error) {
	var d model.EPGData
	if err := json.Unmarshal(raw, &d); err != nil {
		return nil, err
	}
	d.Source = ""
	d.Icon = s.norm.IconURL([]string{cleanCh, oriChannel})

	out, err := json.Marshal(d)
	return json.RawMessage(out), err
}

// convertToLoveTV transforms a DIYP blob into LoveTV format.
func (s *EPGService) convertToLoveTV(raw json.RawMessage, oriChannel, cleanCh, date string) (json.RawMessage, error) {
	var d model.EPGData
	if err := json.Unmarshal(raw, &d); err != nil {
		return nil, err
	}
	icon := s.norm.IconURL([]string{cleanCh, oriChannel})

	programs := make([]model.LoveTVProgram, 0, len(d.EPGData))
	for _, epgItem := range d.EPGData {
		st, _ := parseHHMM(date, epgItem.Start)
		et, _ := parseHHMM(date, epgItem.End)
		dur := et - st
		if dur < 0 {
			dur = 0
		}
		programs = append(programs, model.LoveTVProgram{
			St:       st,
			Et:       et,
			Title:    epgItem.Title,
			ShowTime: formatDuration(dur),
			Duration: dur,
		})
	}

	var isLive string
	var liveSt int64
	now := time.Now().Unix()
	for _, p := range programs {
		if p.St <= now && now <= p.Et {
			isLive = p.Title
			liveSt = p.St
			break
		}
	}

	ch := model.LoveTVChannel{
		IsLive:      isLive,
		LiveSt:      liveSt,
		ChannelName: d.ChannelName,
		LvURL:       d.URL,
		Icon:        icon,
		Program:     programs,
	}

	resp := map[string]model.LoveTVChannel{oriChannel: ch}
	out, err := json.Marshal(resp)
	return json.RawMessage(out), err
}

// defaultDIYP builds a placeholder 24-hour programme schedule.
func (s *EPGService) defaultDIYP(cleanCh, date, _ string) json.RawMessage {
	icon := s.norm.IconURL([]string{cleanCh})
	d := model.EPGData{
		ChannelName: cleanCh,
		Date:        date,
		URL:         "https://github.com/taksssss/iptv-tool",
		Icon:        icon,
	}
	if s.cfg.RetDefault {
		for h := 0; h < 24; h++ {
			d.EPGData = append(d.EPGData, model.EPGProgram{
				Start: fmt.Sprintf("%02d:00", h),
				End:   fmt.Sprintf("%02d:00", (h+1)%24),
				Title: "精彩节目",
				Desc:  "",
			})
		}
	}
	out, _ := json.Marshal(d)
	return json.RawMessage(out)
}

// defaultLoveTV builds a placeholder LoveTV response.
func (s *EPGService) defaultLoveTV(cleanCh, oriChannel string) json.RawMessage {
	icon := s.norm.IconURL([]string{cleanCh, oriChannel})
	var programs []model.LoveTVProgram
	if s.cfg.RetDefault {
		today := time.Now().Format("2006-01-02")
		for h := 0; h < 24; h++ {
			st, _ := parseHHMM(today, fmt.Sprintf("%02d:00", h))
			et, _ := parseHHMM(today, fmt.Sprintf("%02d:00", (h+1)%24))
			programs = append(programs, model.LoveTVProgram{
				St: st, Et: et, Title: "精彩节目", ShowTime: "01:00", Duration: 3600,
			})
		}
	}
	ch := model.LoveTVChannel{
		IsLive:      "",
		ChannelName: cleanCh,
		LvURL:       "https://github.com/taksssss/iptv-tool",
		Icon:        icon,
		Program:     programs,
	}
	resp := map[string]model.LoveTVChannel{oriChannel: ch}
	out, _ := json.Marshal(resp)
	return json.RawMessage(out)
}

// replaceIconBase rewrites /data/icon/ paths to absolute URLs.
func (s *EPGService) replaceIconBase(data string) string {
	if s.serverURL == "" {
		return data
	}
	return strings.ReplaceAll(data, "\"/data/icon/", "\""+s.serverURL+"/data/icon/")
}

// parseHHMM parses "HH:MM" within date and returns Unix timestamp.
func parseHHMM(date, hhmm string) (int64, error) {
	t, err := time.ParseInLocation("2006-01-02 15:04", date+" "+hhmm, time.Local)
	if err != nil {
		return 0, err
	}
	return t.Unix(), nil
}

// formatDuration formats seconds as "HH:MM".
func formatDuration(sec int64) string {
	h := sec / 3600
	m := (sec % 3600) / 60
	return fmt.Sprintf("%02d:%02d", h, m)
}
