package playlist

import (
	"encoding/xml"
	"fmt"
	"strings"
	"time"

	"github.com/taksssss/iptv-tool/go-service/internal/model"
)

// XMLTV root element.
type XMLTV struct {
	XMLName     xml.Name     `xml:"tv"`
	SourceInfoURL string      `xml:"source-info-url,attr,omitempty"`
	Channels    []XMLChannel `xml:"channel"`
	Programmes  []XMLProg    `xml:"programme"`
}

// XMLChannel is an XMLTV <channel> element.
type XMLChannel struct {
	ID          string       `xml:"id,attr"`
	DisplayName []XMLDisplay `xml:"display-name"`
	Icon        *XMLIcon     `xml:"icon,omitempty"`
}

// XMLDisplay is a <display-name> element.
type XMLDisplay struct {
	Lang  string `xml:"lang,attr,omitempty"`
	Value string `xml:",chardata"`
}

// XMLIcon is an <icon> element.
type XMLIcon struct {
	Src string `xml:"src,attr"`
}

// XMLProg is an XMLTV <programme> element.
type XMLProg struct {
	Start   string `xml:"start,attr"`
	Stop    string `xml:"stop,attr"`
	Channel string `xml:"channel,attr"`
	Title   XMLTitle `xml:"title"`
	Desc    *XMLDesc `xml:"desc,omitempty"`
}

// XMLTitle is a <title> element.
type XMLTitle struct {
	Lang  string `xml:"lang,attr,omitempty"`
	Value string `xml:",chardata"`
}

// XMLDesc is a <desc> element.
type XMLDesc struct {
	Lang  string `xml:"lang,attr,omitempty"`
	Value string `xml:",chardata"`
}

// XMLTVOptions controls XMLTV generation.
type XMLTVOptions struct {
	// FilterChannels, if non-empty, restricts output to these channel names.
	FilterChannels []string
	// Dates is the list of dates to include (YYYY-MM-DD). Defaults to today.
	Dates []string
}

// GenerateXMLTV builds an XMLTV document from EPG data grouped by channel.
// epgByDate maps date → slice of EPGData.
func GenerateXMLTV(epgByDate map[string][]*model.EPGData, opts XMLTVOptions) ([]byte, error) {
	filter := make(map[string]struct{})
	for _, ch := range opts.FilterChannels {
		filter[ch] = struct{}{}
	}

	// Collect unique channels
	channelSeen := map[string]bool{}
	var xmlChannels []XMLChannel
	var xmlProgs []XMLProg

	dates := opts.Dates
	if len(dates) == 0 {
		dates = []string{time.Now().Format("2006-01-02")}
	}

	for _, date := range dates {
		rows, ok := epgByDate[date]
		if !ok {
			continue
		}
		for _, row := range rows {
			chName := row.ChannelName
			if len(filter) > 0 {
				if _, ok := filter[chName]; !ok {
					continue
				}
			}
			if !channelSeen[chName] {
				channelSeen[chName] = true
				xc := XMLChannel{
					ID:          chName,
					DisplayName: []XMLDisplay{{Lang: "zh", Value: chName}},
				}
				xmlChannels = append(xmlChannels, xc)
			}

			for _, prog := range row.EPGData {
				start, err := parseProgTime(date, prog.Start)
				if err != nil {
					continue
				}
				end, err := parseProgTime(date, prog.End)
				if err != nil {
					continue
				}
				xp := XMLProg{
					Start:   formatXMLTVTime(start),
					Stop:    formatXMLTVTime(end),
					Channel: chName,
					Title:   XMLTitle{Lang: "zh", Value: prog.Title},
				}
				if prog.Desc != "" {
					xp.Desc = &XMLDesc{Lang: "zh", Value: prog.Desc}
				}
				xmlProgs = append(xmlProgs, xp)
			}
		}
	}

	doc := XMLTV{
		SourceInfoURL: "https://github.com/taksssss/iptv-tool",
		Channels:      xmlChannels,
		Programmes:    xmlProgs,
	}

	var sb strings.Builder
	sb.WriteString(xml.Header)
	enc := xml.NewEncoder(&sb)
	enc.Indent("", "  ")
	if err := enc.Encode(doc); err != nil {
		return nil, fmt.Errorf("xmltv encode: %w", err)
	}
	return []byte(sb.String()), nil
}

// parseProgTime parses "HH:MM" within date in local time.
func parseProgTime(date, hhmm string) (time.Time, error) {
	if hhmm == "" {
		return time.Time{}, fmt.Errorf("empty time")
	}
	return time.ParseInLocation("2006-01-02 15:04", date+" "+hhmm, time.Local)
}

// formatXMLTVTime formats a time in XMLTV format: "20060102150405 +0800".
func formatXMLTVTime(t time.Time) string {
	_, offset := t.Zone()
	sign := "+"
	if offset < 0 {
		sign = "-"
		offset = -offset
	}
	h := offset / 3600
	m := (offset % 3600) / 60
	return t.Format("20060102150405") + fmt.Sprintf(" %s%02d%02d", sign, h, m)
}
