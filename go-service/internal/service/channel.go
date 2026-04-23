package service

import (
	"context"
	"fmt"

	"github.com/taksssss/iptv-tool/go-service/internal/model"
	"github.com/taksssss/iptv-tool/go-service/internal/repository"
)

// ChannelService handles channel business logic.
type ChannelService struct {
	repo *repository.ChannelRepo
}

// NewChannelService constructs a ChannelService.
func NewChannelService(repo *repository.ChannelRepo) *ChannelService {
	return &ChannelService{repo: repo}
}

// ListForConfig returns active channels for the given live_source_config key.
func (s *ChannelService) ListForConfig(ctx context.Context, cfg string) ([]*model.Channel, error) {
	channels, err := s.repo.ListActive(ctx, cfg)
	if err != nil {
		return nil, fmt.Errorf("list channels: %w", err)
	}
	return channels, nil
}
