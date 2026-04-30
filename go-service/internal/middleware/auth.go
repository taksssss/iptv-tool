// Package middleware contains Gin middleware for authentication, logging, and rate-limiting.
package middleware

import (
	"bufio"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/taksssss/iptv-tool/go-service/pkg/config"
)

// requestKind distinguishes EPG/icon requests from live-source requests.
type requestKind int

const (
	kindEPG  requestKind = iota // /epg*, /icon, /?ch=
	kindLive                    // /playlist.m3u, /live/*, /proxy*
)

func detectKind(c *gin.Context) requestKind {
	path := c.FullPath()
	if strings.HasPrefix(path, "/playlist") ||
		strings.HasPrefix(path, "/live") ||
		strings.HasPrefix(path, "/proxy") {
		return kindLive
	}
	return kindEPG
}

// Auth returns a Gin middleware that enforces token, User-Agent, and IP controls
// as defined in config.  Access decisions mirror the PHP index.php logic.
func Auth(cfg *config.Config) gin.HandlerFunc {
	return func(c *gin.Context) {
		kind := detectKind(c)
		isLive := kind == kindLive

		// ---- Token check ------------------------------------------------
		if cfg.TokenRange != 0 {
			token := c.Query("token")
			if !isAllowed(token, cfg.Tokens, cfg.TokenRange, isLive) {
				c.AbortWithStatusJSON(http.StatusForbidden, gin.H{"error": "访问被拒绝：无效Token。"})
				return
			}
		}

		// ---- User-Agent check -------------------------------------------
		if cfg.UserAgentRange != 0 {
			ua := c.GetHeader("User-Agent")
			if !isAllowed(ua, cfg.UserAgents, cfg.UserAgentRange, isLive) {
				c.AbortWithStatusJSON(http.StatusForbidden, gin.H{"error": "访问被拒绝：无效UA。"})
				return
			}
		}

		// ---- IP check ---------------------------------------------------
		if cfg.IPListMode != 0 {
			clientIP := realIP(c)
			list := loadIPList(cfg.IPListFile)
			hit := ipListHit(clientIP, list)
			if (cfg.IPListMode == 1 && !hit) || (cfg.IPListMode == 2 && hit) {
				c.AbortWithStatusJSON(http.StatusForbidden, gin.H{"error": "访问被拒绝：IP不允许。"})
				return
			}
		}

		c.Next()
	}
}

// isAllowed mirrors the PHP isAllowed() function.
//
//	range 0 → disabled
//	range 1 → only EPG endpoints require auth
//	range 2 → only live endpoints require auth
//	range 3 → all endpoints require auth
func isAllowed(value string, list []string, rangeMode int, isLive bool) bool {
	for _, allowed := range list {
		if strings.EqualFold(value, allowed) {
			return true
		}
	}
	// If not in list, check whether this endpoint type needs auth.
	switch rangeMode {
	case 1: // EPG-only auth; live is free
		return isLive
	case 2: // live-only auth; EPG is free
		return !isLive
	default: // 3 = all require auth
		return false
	}
}

// realIP extracts the real client IP, honouring X-Forwarded-For.
func realIP(c *gin.Context) string {
	if xff := c.GetHeader("X-Forwarded-For"); xff != "" {
		return strings.TrimSpace(strings.SplitN(xff, ",", 2)[0])
	}
	if xci := c.GetHeader("X-Client-IP"); xci != "" {
		return xci
	}
	ip, _, _ := net.SplitHostPort(c.Request.RemoteAddr)
	return ip
}

// loadIPList reads the IP list file, ignoring blank lines and comments.
func loadIPList(path string) []string {
	f, err := os.Open(filepath.Clean(path))
	if err != nil {
		return nil
	}
	defer f.Close()

	var list []string
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		list = append(list, line)
	}
	return list
}

// ipListHit returns true if clientIP matches any rule in the list.
// Rules may be plain IPs, glob wildcards, or CIDR ranges.
func ipListHit(clientIP string, rules []string) bool {
	for _, rule := range rules {
		if strings.Contains(rule, "/") {
			if inCIDR(clientIP, rule) {
				return true
			}
		} else if fnmatch(rule, clientIP) {
			return true
		}
	}
	return false
}

// inCIDR returns true if ip is inside the CIDR block.
func inCIDR(ip, cidr string) bool {
	_, network, err := net.ParseCIDR(cidr)
	if err != nil {
		return false
	}
	parsed := net.ParseIP(ip)
	if parsed == nil {
		return false
	}
	return network.Contains(parsed)
}

// fnmatch implements a minimal shell-style wildcard match (only * supported).
func fnmatch(pattern, s string) bool {
	for len(pattern) > 0 {
		switch pattern[0] {
		case '*':
			for len(pattern) > 0 && pattern[0] == '*' {
				pattern = pattern[1:]
			}
			if len(pattern) == 0 {
				return true
			}
			for i := range s {
				if fnmatch(pattern, s[i:]) {
					return true
				}
			}
			return false
		default:
			if len(s) == 0 || pattern[0] != s[0] {
				return false
			}
			pattern = pattern[1:]
			s = s[1:]
		}
	}
	return len(s) == 0
}

// ---- RequestLogger middleware -------------------------------------------

// RequestLogger records every request to the access_log table via an injected callback.
func RequestLogger(logFn func(accessTime, clientIP, method, url, ua string, denied int, denyMsg string)) gin.HandlerFunc {
	return func(c *gin.Context) {
		c.Next()

		denied := 0
		denyMsg := ""
		if c.Writer.Status() == http.StatusForbidden {
			denied = 1
			if last := c.Errors.Last(); last != nil {
				denyMsg = last.Error()
			}
		}

		logFn(
			time.Now().Format("2006-01-02 15:04:05"),
			realIP(c),
			c.Request.Method,
			c.Request.RequestURI,
			c.GetHeader("User-Agent"),
			denied,
			denyMsg,
		)
	}
}

// ---- Rate limiter -------------------------------------------------------

// tokenBucket is a naive per-IP token-bucket limiter backed by a sync.Map.
// For production use replace with redis INCR sliding window.
type tokenBucket struct {
	tokens   float64
	lastSeen time.Time
}

// RateLimiter returns a Gin middleware that limits each IP to rps requests/sec.
// burstSize controls the burst allowance.
func RateLimiter(rps float64, burstSize int) gin.HandlerFunc {
	type entry struct {
		tokens   float64
		lastTime time.Time
	}
	var mu sync.Map

	return func(c *gin.Context) {
		ip := realIP(c)
		now := time.Now()

		actual, _ := mu.LoadOrStore(ip, &entry{tokens: float64(burstSize), lastTime: now})
		e := actual.(*entry)

		// Refill tokens
		elapsed := now.Sub(e.lastTime).Seconds()
		e.tokens += elapsed * rps
		if e.tokens > float64(burstSize) {
			e.tokens = float64(burstSize)
		}
		e.lastTime = now

		if e.tokens < 1 {
			c.AbortWithStatus(http.StatusTooManyRequests)
			return
		}
		e.tokens--
		c.Next()
	}
}
