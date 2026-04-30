package playlist_test

import (
	"strings"
	"testing"

	"github.com/taksssss/iptv-tool/go-service/internal/model"
	"github.com/taksssss/iptv-tool/go-service/internal/playlist"
)

func TestGenerateM3U_Basic(t *testing.T) {
	channels := []*model.Channel{
		{
			GroupTitle:  "央视",
			ChannelName: "CCTV1",
			TvgName:     "CCTV-1",
			TvgID:       "cctv1",
			StreamURL:   "http://stream.example.com/cctv1.m3u8",
		},
	}

	opts := playlist.M3UOptions{
		ServerURL: "http://localhost:8080",
		EPGPath:   "/epg.xml",
	}
	out := playlist.GenerateM3U(channels, opts)

	if !strings.Contains(out, "#EXTM3U") {
		t.Error("missing #EXTM3U header")
	}
	if !strings.Contains(out, "CCTV1") {
		t.Error("missing channel name")
	}
	if !strings.Contains(out, "http://stream.example.com/cctv1.m3u8") {
		t.Error("missing stream URL")
	}
	if !strings.Contains(out, "x-tvg-url=") {
		t.Error("missing tvg-url")
	}
}

func TestGenerateM3U_WithToken(t *testing.T) {
	channels := []*model.Channel{
		{ChannelName: "Test", StreamURL: "http://stream.test/live.m3u8"},
	}
	opts := playlist.M3UOptions{
		ServerURL: "http://host",
		EPGPath:   "/epg.xml",
		Token:     "mytoken",
	}
	out := playlist.GenerateM3U(channels, opts)
	if !strings.Contains(out, "token=mytoken") {
		t.Error("token not appended to stream URL")
	}
}

func TestGenerateM3U_DisabledChannel(t *testing.T) {
	channels := []*model.Channel{
		{ChannelName: "Active", StreamURL: "http://a.test/s.m3u8", Disable: 0},
		{ChannelName: "Disabled", StreamURL: "http://b.test/s.m3u8", Disable: 1},
	}
	opts := playlist.M3UOptions{ServerURL: "http://host", EPGPath: "/epg.xml"}
	out := playlist.GenerateM3U(channels, opts)

	if strings.Contains(out, "Disabled") {
		t.Error("disabled channel should not appear")
	}
	if !strings.Contains(out, "Active") {
		t.Error("active channel should appear")
	}
}

func TestGenerateTXT_GroupHeaders(t *testing.T) {
	channels := []*model.Channel{
		{GroupTitle: "央视", ChannelName: "CCTV1", StreamURL: "http://stream/cctv1"},
		{GroupTitle: "央视", ChannelName: "CCTV2", StreamURL: "http://stream/cctv2"},
		{GroupTitle: "卫视", ChannelName: "湖南卫视", StreamURL: "http://stream/hunan"},
	}
	opts := playlist.M3UOptions{ServerURL: "http://host"}
	out := playlist.GenerateTXT(channels, opts)

	if !strings.Contains(out, "央视,#genre#") {
		t.Error("missing group header for 央视")
	}
	if !strings.Contains(out, "卫视,#genre#") {
		t.Error("missing group header for 卫视")
	}
	if strings.Count(out, "央视,#genre#") != 1 {
		t.Error("group header should appear exactly once")
	}
}
