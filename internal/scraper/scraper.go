package scraper

import (
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"time"
)

// SourceHandler is a function that scrapes data from a source
type SourceHandler func(url string) (map[string]*ChannelData, error)

// ChannelData represents EPG data for a channel
type ChannelData struct {
	ChannelName  string                       `json:"channel_name"`
	DIYPData     map[string][]ProgramEntry    `json:"diyp_data"`
	ProcessCount int                          `json:"process_count"`
}

// ProgramEntry represents a single program entry
type ProgramEntry struct {
	Start string `json:"start"`
	End   string `json:"end"`
	Title string `json:"title"`
	Desc  string `json:"desc"`
}

// SourceMatcher is a function that checks if a URL matches a source
type SourceMatcher func(url string) bool

// SourceConfig represents a scraper source configuration
type SourceConfig struct {
	Name    string
	Match   SourceMatcher
	Handler SourceHandler
}

// Registry holds all registered scrapers
var Registry = []SourceConfig{
	{
		Name:    "tvmao",
		Match:   func(url string) bool { return len(url) > 5 && url[:5] == "tvmao" },
		Handler: tvmaoHandler,
	},
	{
		Name:    "cntv",
		Match:   func(url string) bool { return len(url) > 4 && url[:4] == "cntv" },
		Handler: cntvHandler,
	},
}

// GetHandler returns the appropriate handler for a URL
func GetHandler(url string) (SourceHandler, error) {
	for _, source := range Registry {
		if source.Match(url) {
			return source.Handler, nil
		}
	}
	return nil, fmt.Errorf("no handler found for URL: %s", url)
}

// tvmaoHandler handles TVMao data source
func tvmaoHandler(url string) (map[string]*ChannelData, error) {
	// TODO: Implement TVMao scraping logic
	// This is a placeholder implementation
	return map[string]*ChannelData{}, nil
}

// cntvHandler handles CNTV data source
func cntvHandler(url string) (map[string]*ChannelData, error) {
	// TODO: Implement CNTV scraping logic
	// This is a placeholder implementation
	return map[string]*ChannelData{}, nil
}

// FetchURL fetches content from a URL
func FetchURL(url string, timeout time.Duration) ([]byte, error) {
	client := &http.Client{
		Timeout: timeout,
	}

	resp, err := client.Get(url)
	if err != nil {
		return nil, fmt.Errorf("failed to fetch URL: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("bad status code: %d", resp.StatusCode)
	}

	body, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, fmt.Errorf("failed to read response: %w", err)
	}

	return body, nil
}

// ParseJSON parses JSON data into a map
func ParseJSON(data []byte) (map[string]interface{}, error) {
	var result map[string]interface{}
	if err := json.Unmarshal(data, &result); err != nil {
		return nil, fmt.Errorf("failed to parse JSON: %w", err)
	}
	return result, nil
}
