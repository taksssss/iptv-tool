package config

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
)

// Config represents the application configuration
type Config struct {
	DataDir              string                 `json:"-"`
	XMLUrls              []string               `json:"xml_urls"`
	DaysToKeep           int                    `json:"days_to_keep"`
	StartTime            string                 `json:"start_time"`
	EndTime              string                 `json:"end_time"`
	ChannelMappings      map[string]string      `json:"channel_mappings"`
	ChannelIgnoreChars   string                 `json:"channel_ignore_chars"`
	ChannelBindEPG       []interface{}          `json:"channel_bind_epg"`
	GenXML               int                    `json:"gen_xml"`
	IncludeFutureOnly    int                    `json:"include_future_only"`
	RetDefault           int                    `json:"ret_default"`
	ChtToChs             int                    `json:"cht_to_chs"`
	DBType               string                 `json:"db_type"`
	CachedType           string                 `json:"cached_type"`
	GenListEnable        int                    `json:"gen_list_enable"`
	IntervalTime         int                    `json:"interval_time"`
	MySQL                MySQLConfig            `json:"mysql"`
	Redis                RedisConfig            `json:"redis"`
	LiveSourceAutoSync   int                    `json:"live_source_auto_sync"`
	CheckSpeedAutoSync   int                    `json:"check_speed_auto_sync"`
	CheckSpeedIntervalFactor int                `json:"check_speed_interval_factor"`
	LiveSourceConfig     string                 `json:"live_source_config"`
	LiveChannelNameProcess int                  `json:"live_channel_name_process"`
	LiveTvgLogoEnable    int                    `json:"live_tvg_logo_enable"`
	LiveTvgIDEnable      int                    `json:"live_tvg_id_enable"`
	LiveTvgNameEnable    int                    `json:"live_tvg_name_enable"`
	LiveFuzzyMatch       int                    `json:"live_fuzzy_match"`
	GenLiveUpdateTime    int                    `json:"gen_live_update_time"`
	M3UIconFirst         int                    `json:"m3u_icon_first"`
	LiveURLComment       int                    `json:"live_url_comment"`
	Ku9SecondaryGrouping int                    `json:"ku9_secondary_grouping"`
	CheckIPv6            int                    `json:"check_ipv6"`
	MinResolutionWidth   int                    `json:"min_resolution_width"`
	MinResolutionHeight  int                    `json:"min_resolution_height"`
	URLsLimit            int                    `json:"urls_limit"`
	SortByDelay          int                    `json:"sort_by_delay"`
	Token                string                 `json:"token"`
	TokenRange           int                    `json:"token_range"`
	UserAgent            string                 `json:"user_agent"`
	UserAgentRange       int                    `json:"user_agent_range"`
	DefaultIcon          string                 `json:"default_icon"`
	TargetTimeZone       int                    `json:"target_time_zone"`
	IPListMode           int                    `json:"ip_list_mode"`
	CheckUpdate          int                    `json:"check_update"`
	DebugMode            int                    `json:"debug_mode"`
	ManagePassword       string                 `json:"manage_password"`
}

// MySQLConfig represents MySQL configuration
type MySQLConfig struct {
	Host     string `json:"host"`
	DBName   string `json:"dbname"`
	Username string `json:"username"`
	Password string `json:"password"`
}

// RedisConfig represents Redis configuration
type RedisConfig struct {
	Host     string `json:"host"`
	Port     string `json:"port"`
	Password string `json:"password"`
}

// Load loads the configuration from the data directory
func Load(dataDir string) (*Config, error) {
	configPath := filepath.Join(dataDir, "config.json")
	
	// Create default config if not exists
	if _, err := os.Stat(configPath); os.IsNotExist(err) {
		defaultConfigPath := filepath.Join("epg", "assets", "defaultConfig.json")
		if err := copyFile(defaultConfigPath, configPath); err != nil {
			// If default doesn't exist, create a minimal config
			cfg := getDefaultConfig()
			if err := cfg.Save(configPath); err != nil {
				return nil, fmt.Errorf("failed to create default config: %w", err)
			}
			cfg.DataDir = dataDir
			return cfg, nil
		}
	}

	// Load configuration
	data, err := os.ReadFile(configPath)
	if err != nil {
		return nil, fmt.Errorf("failed to read config file: %w", err)
	}

	cfg := &Config{}
	if err := json.Unmarshal(data, cfg); err != nil {
		return nil, fmt.Errorf("failed to parse config file: %w", err)
	}

	cfg.DataDir = dataDir
	return cfg, nil
}

// Save saves the configuration to file
func (c *Config) Save(path string) error {
	data, err := json.MarshalIndent(c, "", "    ")
	if err != nil {
		return fmt.Errorf("failed to marshal config: %w", err)
	}

	if err := os.WriteFile(path, data, 0644); err != nil {
		return fmt.Errorf("failed to write config file: %w", err)
	}

	return nil
}

// getDefaultConfig returns a default configuration
func getDefaultConfig() *Config {
	return &Config{
		XMLUrls:         []string{},
		DaysToKeep:      7,
		StartTime:       "00:00",
		EndTime:         "23:59",
		ChannelMappings: map[string]string{
			"CCTV$1": "regex:/^CCTV[-\\s]*(\\d{1,2}(\\s*P(LUS)?|[K\\+])?)(?![\\s-]*(美洲|欧洲)).*/i",
		},
		ChannelIgnoreChars:       "&nbsp, -",
		ChannelBindEPG:           []interface{}{},
		GenXML:                   1,
		IncludeFutureOnly:        1,
		RetDefault:               0,
		ChtToChs:                 1,
		DBType:                   "sqlite",
		CachedType:               "memcached",
		GenListEnable:            0,
		IntervalTime:             21600,
		MySQL: MySQLConfig{
			Host:     "mysql",
			DBName:   "phpepg",
			Username: "phpepg",
			Password: "phpepg",
		},
		Redis: RedisConfig{
			Host:     "",
			Port:     "",
			Password: "",
		},
		LiveSourceAutoSync:       0,
		CheckSpeedAutoSync:       0,
		CheckSpeedIntervalFactor: 1,
		LiveSourceConfig:         "default",
		LiveChannelNameProcess:   0,
		LiveTvgLogoEnable:        1,
		LiveTvgIDEnable:          1,
		LiveTvgNameEnable:        1,
		LiveFuzzyMatch:           1,
		GenLiveUpdateTime:        0,
		M3UIconFirst:             0,
		LiveURLComment:           0,
		Ku9SecondaryGrouping:     0,
		CheckIPv6:                0,
		MinResolutionWidth:       0,
		MinResolutionHeight:      0,
		URLsLimit:                0,
		SortByDelay:              0,
		Token:                    "",
		TokenRange:               1,
		UserAgent:                "",
		UserAgentRange:           0,
		DefaultIcon:              "",
		TargetTimeZone:           0,
		IPListMode:               0,
		CheckUpdate:              1,
		DebugMode:                0,
		ManagePassword:           "",
	}
}

// copyFile copies a file from src to dst
func copyFile(src, dst string) error {
	data, err := os.ReadFile(src)
	if err != nil {
		return err
	}
	return os.WriteFile(dst, data, 0644)
}
