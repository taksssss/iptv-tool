package config

import (
	"encoding/json"
	"fmt"
	"os"
	"strconv"
	"strings"
)

// Config holds all application configuration.
type Config struct {
	// Server
	ListenAddr string // :8080

	// Database
	DBType   string // sqlite | mysql
	DBDSN    string // SQLite path or MySQL DSN

	// Redis
	RedisAddr     string
	RedisPassword string
	RedisDB       int

	// Cache
	CacheType string // redis | memory | none
	CacheTTL  int    // seconds, default 86400

	// Auth
	Tokens          []string // allowed tokens
	TokenRange      int      // 0=off 1=epg-only 2=live-only 3=both
	UserAgents      []string // allowed user-agents
	UserAgentRange  int      // 0=off 1=epg-only 2=live-only 3=both
	IPListMode      int      // 0=off 1=whitelist 2=blacklist
	IPListFile      string   // path to ip list file

	// Proxy
	ProxyToken     string // AES key token for URL encryption
	ProxyTimeout   int    // upstream connect timeout in seconds

	// Playlist
	ServerURL      string // public base URL e.g. http://1.2.3.4:8080
	TVGUrl         string // overridden EPG URL for m3u header

	// Logging
	AccessLogEnable bool
	LogLevel        string // debug | info | warn | error

	// Misc
	DataDir        string // /data
	DefaultIcon    string
	RetDefault     bool
}

// Load reads configuration from environment variables with sensible defaults.
func Load() (*Config, error) {
	c := &Config{
		ListenAddr:      getEnv("LISTEN_ADDR", ":8080"),
		DBType:          getEnv("DB_TYPE", "sqlite"),
		DBDSN:           getEnv("DB_DSN", "./data/data.db"),
		RedisAddr:       getEnv("REDIS_ADDR", "127.0.0.1:6379"),
		RedisPassword:   getEnv("REDIS_PASSWORD", ""),
		RedisDB:         getEnvInt("REDIS_DB", 0),
		CacheType:       getEnv("CACHE_TYPE", "redis"),
		CacheTTL:        getEnvInt("CACHE_TTL", 86400),
		TokenRange:      getEnvInt("TOKEN_RANGE", 1),
		UserAgentRange:  getEnvInt("USER_AGENT_RANGE", 0),
		IPListMode:      getEnvInt("IP_LIST_MODE", 0),
		IPListFile:      getEnv("IP_LIST_FILE", "./data/ipList.txt"),
		ProxyToken:      getEnv("PROXY_TOKEN", ""),
		ProxyTimeout:    getEnvInt("PROXY_TIMEOUT", 10),
		ServerURL:       getEnv("SERVER_URL", ""),
		AccessLogEnable: getEnvBool("ACCESS_LOG_ENABLE", true),
		LogLevel:        getEnv("LOG_LEVEL", "info"),
		DataDir:         getEnv("DATA_DIR", "./data"),
		DefaultIcon:     getEnv("DEFAULT_ICON", ""),
		RetDefault:      getEnvBool("RET_DEFAULT", true),
	}

	// Parse token list (newline/comma separated)
	if raw := getEnv("TOKENS", ""); raw != "" {
		c.Tokens = splitLines(raw)
	}
	if raw := getEnv("USER_AGENTS", ""); raw != "" {
		c.UserAgents = splitLines(raw)
	}

	// Validate DB type
	if c.DBType != "sqlite" && c.DBType != "mysql" {
		return nil, fmt.Errorf("unsupported DB_TYPE %q (must be sqlite or mysql)", c.DBType)
	}

	return c, nil
}

// LoadFromFile merges a JSON config file (PHP-compatible format) into Config.
func LoadFromFile(path string, c *Config) error {
	f, err := os.Open(path)
	if err != nil {
		return err
	}
	defer f.Close()

	var raw map[string]json.RawMessage
	if err := json.NewDecoder(f).Decode(&raw); err != nil {
		return err
	}

	if v, ok := raw["token"]; ok {
		var s string
		_ = json.Unmarshal(v, &s)
		c.Tokens = splitLines(s)
		c.ProxyToken = strings.TrimSpace(splitLines(s)[0])
	}
	if v, ok := raw["token_range"]; ok {
		var n int
		_ = json.Unmarshal(v, &n)
		c.TokenRange = n
	}
	if v, ok := raw["user_agent"]; ok {
		var s string
		_ = json.Unmarshal(v, &s)
		c.UserAgents = splitLines(s)
	}
	if v, ok := raw["user_agent_range"]; ok {
		var n int
		_ = json.Unmarshal(v, &n)
		c.UserAgentRange = n
	}
	if v, ok := raw["ip_list_mode"]; ok {
		var n int
		_ = json.Unmarshal(v, &n)
		c.IPListMode = n
	}
	if v, ok := raw["default_icon"]; ok {
		var s string
		_ = json.Unmarshal(v, &s)
		c.DefaultIcon = s
	}
	if v, ok := raw["ret_default"]; ok {
		var b bool
		_ = json.Unmarshal(v, &b)
		c.RetDefault = b
	}
	return nil
}

func getEnv(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func getEnvInt(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

func getEnvBool(key string, def bool) bool {
	if v := os.Getenv(key); v != "" {
		b, err := strconv.ParseBool(v)
		if err == nil {
			return b
		}
	}
	return def
}

func splitLines(s string) []string {
	var out []string
	for _, line := range strings.Split(s, "\n") {
		line = strings.TrimSpace(line)
		if line != "" {
			out = append(out, line)
		}
	}
	return out
}
