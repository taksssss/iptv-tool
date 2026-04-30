// Package playlist provides M3U and XMLTV generation utilities.
package playlist

import (
	"fmt"
	"net/url"
	"strings"

	"github.com/taksssss/iptv-tool/go-service/internal/model"
)

// M3UOptions controls M3U output.
type M3UOptions struct {
	// ServerURL is the base URL used in tvg-url header and logo rewrites.
	ServerURL string
	// Token, if set, is appended as ?token= to each stream URL.
	Token string
	// EPGPath is the path to the EPG endpoint (e.g. "/epg.xml").
	EPGPath string
	// ProxyMode wraps every stream URL through the proxy endpoint.
	ProxyMode bool
	// ProxyToken is the AES key for URL encryption in proxy mode.
	ProxyToken string
	// EncryptFn encrypts a stream URL for proxy mode.
	EncryptFn func(rawURL, token string) string
}

// GenerateM3U produces an M3U playlist from a slice of channels.
func GenerateM3U(channels []*model.Channel, opts M3UOptions) string {
	var sb strings.Builder

	tvgURL := opts.ServerURL + opts.EPGPath
	if opts.Token != "" {
		tvgURL += "?token=" + url.QueryEscape(opts.Token)
	}
	sb.WriteString(fmt.Sprintf("#EXTM3U x-tvg-url=\"%s\"\n\n", tvgURL))

	for _, ch := range channels {
		if ch.Disable != 0 {
			continue
		}

		name := ch.ChannelName
		tvgName := ch.TvgName
		if tvgName == "" {
			tvgName = name
		}
		tvgID := ch.TvgID
		group := ch.GroupTitle
		logo := ch.IconURL
		// Rewrite relative icon paths
		if strings.HasPrefix(logo, "/data/icon/") {
			logo = opts.ServerURL + logo
		}

		streamURL := ch.StreamURL
		if opts.ProxyMode && opts.EncryptFn != nil && streamURL != "" {
			enc := opts.EncryptFn(streamURL, opts.ProxyToken)
			streamURL = opts.ServerURL + "/proxy?url=" + url.QueryEscape(enc)
		}
		if opts.Token != "" && streamURL != "" {
			sep := "?"
			if strings.Contains(streamURL, "?") {
				sep = "&"
			}
			streamURL += sep + "token=" + url.QueryEscape(opts.Token)
		}

		sb.WriteString(fmt.Sprintf(
			"#EXTINF:-1 tvg-id=\"%s\" tvg-name=\"%s\" tvg-logo=\"%s\" group-title=\"%s\",%s\n%s\n\n",
			tvgID, tvgName, logo, group, name, streamURL,
		))
	}
	return sb.String()
}

// GenerateTXT produces a plain-text channel list (name,url per line, grouped).
func GenerateTXT(channels []*model.Channel, opts M3UOptions) string {
	var sb strings.Builder
	currentGroup := ""

	for _, ch := range channels {
		if ch.Disable != 0 {
			continue
		}

		if ch.GroupTitle != currentGroup {
			currentGroup = ch.GroupTitle
			sb.WriteString(currentGroup + ",#genre#\n")
		}

		streamURL := ch.StreamURL
		if opts.ProxyMode && opts.EncryptFn != nil && streamURL != "" {
			enc := opts.EncryptFn(streamURL, opts.ProxyToken)
			streamURL = opts.ServerURL + "/proxy?url=" + url.QueryEscape(enc)
		}

		sb.WriteString(fmt.Sprintf("%s,%s\n", ch.ChannelName, streamURL))
	}
	return sb.String()
}
