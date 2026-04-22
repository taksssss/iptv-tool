package model

// Channel represents a row in the channels table.
type Channel struct {
	GroupPrefix    string
	GroupTitle     string
	ChannelName    string
	ChsChannelName string
	StreamURL      string
	IconURL        string
	TvgID          string
	TvgName        string
	Disable        int
	Modified       int
	Source         string
	Tag            string
	Config         string
}

// ChannelInfo holds speed-test results for a stream URL.
type ChannelInfo struct {
	StreamURL  string
	Resolution string
	Speed      string
}
