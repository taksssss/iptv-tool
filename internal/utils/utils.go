package utils

import (
	"crypto/md5"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"regexp"
	"strings"

	"github.com/liuzl/gocc"
	"github.com/taksssss/iptv-tool/internal/config"
)

// GetClientIP gets the client IP address from the request
func GetClientIP(r *http.Request) string {
	// Check X-Forwarded-For header
	if xff := r.Header.Get("X-Forwarded-For"); xff != "" {
		ips := strings.Split(xff, ",")
		return strings.TrimSpace(ips[0])
	}

	// Check X-Real-IP header
	if xri := r.Header.Get("X-Real-IP"); xri != "" {
		return xri
	}

	// Use RemoteAddr
	ip := r.RemoteAddr
	if idx := strings.LastIndex(ip, ":"); idx != -1 {
		ip = ip[:idx]
	}

	return ip
}

// GetServerURL constructs the server URL from the request
func GetServerURL(r *http.Request) string {
	scheme := "http"
	if r.TLS != nil || r.Header.Get("X-Forwarded-Proto") == "https" {
		scheme = "https"
	}

	host := r.Host
	if fh := r.Header.Get("X-Forwarded-Host"); fh != "" {
		host = fh
	}

	return fmt.Sprintf("%s://%s", scheme, host)
}

// FileExists checks if a file exists
func FileExists(path string) bool {
	_, err := os.Stat(path)
	return !os.IsNotExist(err)
}

// ReadFile reads a file and returns its content
func ReadFile(path string) (string, error) {
	data, err := os.ReadFile(path)
	if err != nil {
		return "", err
	}
	return string(data), nil
}

// MD5Hash calculates the MD5 hash of a string
func MD5Hash(text string) string {
	hash := md5.Sum([]byte(text))
	return hex.EncodeToString(hash[:])
}

// CleanChannelName cleans and normalizes a channel name
func CleanChannelName(channel string, t2s bool, cfg *config.Config) string {
	if channel == "" {
		return ""
	}

	// Get ignore characters
	ignoreChars := strings.Split(strings.ReplaceAll(cfg.ChannelIgnoreChars, "&nbsp", " "), ",")
	for i := range ignoreChars {
		ignoreChars[i] = strings.TrimSpace(ignoreChars[i])
	}

	normalizedChannel := channel
	for _, char := range ignoreChars {
		normalizedChannel = strings.ReplaceAll(normalizedChannel, char, "")
	}

	// Apply channel mappings (with regex support)
	for replace, search := range cfg.ChannelMappings {
		if strings.HasPrefix(search, "regex:") {
			pattern := strings.TrimPrefix(search, "regex:")
			re, err := regexp.Compile(pattern)
			if err == nil && re.MatchString(channel) {
				return strings.ToUpper(re.ReplaceAllString(channel, replace))
			}
		} else {
			channels := strings.Split(search, ",")
			for _, singleChannel := range channels {
				singleChannel = strings.TrimSpace(singleChannel)
				normalizedSearch := singleChannel
				for _, char := range ignoreChars {
					normalizedSearch = strings.ReplaceAll(normalizedSearch, char, "")
				}
				if strings.EqualFold(normalizedChannel, normalizedSearch) {
					return strings.ToUpper(replace)
				}
			}
		}
	}

	// Traditional to Simplified Chinese conversion
	if t2s {
		normalizedChannel = T2S(normalizedChannel)
	}

	return strings.ToUpper(normalizedChannel)
}

// LoadIconList loads the icon list from file
func LoadIconList(dataDir string) map[string]string {
	iconListPath := dataDir + "/iconList.json"
	defaultIconListPath := "epg/assets/defaultIconList.json"

	iconList := make(map[string]string)
	defaultIconList := make(map[string]string)

	// Load default icon list
	if data, err := os.ReadFile(defaultIconListPath); err == nil {
		json.Unmarshal(data, &defaultIconList)
	}

	// Load custom icon list
	if data, err := os.ReadFile(iconListPath); err == nil {
		json.Unmarshal(data, &iconList)
	}

	// Merge (custom overrides default)
	for k, v := range iconList {
		defaultIconList[k] = v
	}

	return defaultIconList
}

// IconURLMatch matches a channel name to an icon URL
func IconURLMatch(channel string, iconList map[string]string, defaultIcon string) string {
	// Exact match
	if icon, ok := iconList[channel]; ok {
		return icon
	}

	var bestMatch string
	var iconURL string

	// Forward fuzzy match (channel name contains in icon list key)
	for channelName, icon := range iconList {
		if strings.Contains(strings.ToLower(channelName), strings.ToLower(channel)) {
			if bestMatch == "" || len(channelName) < len(bestMatch) {
				bestMatch = channelName
				iconURL = icon
			}
		}
	}

	if iconURL != "" {
		return iconURL
	}

	// Reverse fuzzy match (icon list key contains in channel name)
	for channelName, icon := range iconList {
		if strings.Contains(strings.ToLower(channel), strings.ToLower(channelName)) {
			if bestMatch == "" || len(channelName) > len(bestMatch) {
				bestMatch = channelName
				iconURL = icon
			}
		}
	}

	if iconURL != "" {
		return iconURL
	}

	// Return default icon if no match
	return defaultIcon
}

// LoadIPList loads the IP list from file
func LoadIPList(dataDir string, mode int) []string {
	filename := "ipBlackList.txt"
	if mode == 2 {
		filename = "ipWhiteList.txt"
	}

	filePath := dataDir + "/" + filename
	data, err := os.ReadFile(filePath)
	if err != nil {
		return []string{}
	}

	lines := strings.Split(string(data), "\n")
	ips := []string{}
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line != "" {
			ips = append(ips, line)
		}
	}

	return ips
}

// IPInList checks if an IP is in the list
func IPInList(ip string, list []string) bool {
	for _, item := range list {
		if item == ip {
			return true
		}
		// TODO: Add CIDR range support
	}
	return false
}

// ParseSourceInfo parses source information (placeholder)
func ParseSourceInfo(url string, cfg *config.Config, db interface{}, dataDir string) error {
	// TODO: Implement source parsing logic
	return fmt.Errorf("not implemented")
}

// DownloadFile downloads a file from a URL
func DownloadFile(url string, timeout int) ([]byte, error) {
	client := &http.Client{
		Timeout: 0, // No timeout for streaming
	}

	resp, err := client.Get(url)
	if err != nil {
		return nil, err
	}
	defer resp.Body.Close()

	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("bad status: %s", resp.Status)
	}

	return io.ReadAll(resp.Body)
}

// T2S converts Traditional Chinese to Simplified Chinese
func T2S(text string) string {
	cc, err := gocc.New("t2s")
	if err != nil {
		// If conversion fails, return original text
		return text
	}
	
	result, err := cc.Convert(text)
	if err != nil {
		return text
	}
	
	return result
}
