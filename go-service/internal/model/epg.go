package model

import "encoding/json"

// EPGProgram is a single programme slot in DIYP/百川 format.
type EPGProgram struct {
	Start string `json:"start"`
	End   string `json:"end"`
	Title string `json:"title"`
	Desc  string `json:"desc"`
}

// EPGData is the JSON blob stored in epg_data.epg_diyp.
type EPGData struct {
	ChannelName string       `json:"channel_name"`
	Date        string       `json:"date"`
	URL         string       `json:"url"`
	Source      string       `json:"source,omitempty"`
	Icon        string       `json:"icon,omitempty"`
	EPGData     []EPGProgram `json:"epg_data"`
}

// LoveTVProgram is a single programme in 超级直播/LoveTV format.
type LoveTVProgram struct {
	St        int64  `json:"st"`
	Et        int64  `json:"et"`
	EventType string `json:"eventType"`
	EventID   string `json:"eventId"`
	Title     string `json:"t"`
	ShowTime  string `json:"showTime"`
	Duration  int64  `json:"duration"`
}

// LoveTVChannel is the top-level LoveTV response structure.
type LoveTVChannel struct {
	IsLive      string          `json:"isLive"`
	LiveSt      int64           `json:"liveSt"`
	ChannelName string          `json:"channelName"`
	LvURL       string          `json:"lvUrl"`
	Icon        string          `json:"icon"`
	Program     []LoveTVProgram `json:"program"`
}

// EPGRow is a database row from epg_data.
type EPGRow struct {
	Date      string
	Channel   string
	EPGDiypRaw json.RawMessage
}
