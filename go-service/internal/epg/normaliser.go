// Package epg provides channel-name normalisation and icon matching,
// mirroring the PHP cleanChannelName() and iconUrlMatch() functions.
package epg

import (
	"strings"
	"sync"
	"unicode"
)

// IgnoreChars is the set of characters stripped from channel names before matching.
var defaultIgnoreChars = []string{" ", "-", "\u00a0"}

// Normaliser cleans channel names and resolves icon URLs.
type Normaliser struct {
	mu          sync.RWMutex
	ignoreChars []string
	mappings    map[string]string // replace → search (PHP config)
	iconList    map[string]string // channelName → icon URL
	defaultIcon string
}

// NewNormaliser creates a Normaliser with the given configuration.
func NewNormaliser(ignoreChars []string, mappings, iconList map[string]string, defaultIcon string) *Normaliser {
	ic := ignoreChars
	if len(ic) == 0 {
		ic = defaultIgnoreChars
	}
	return &Normaliser{
		ignoreChars: ic,
		mappings:    mappings,
		iconList:    iconList,
		defaultIcon: defaultIcon,
	}
}

// UpdateIcons replaces the icon list atomically (called after config reload).
func (n *Normaliser) UpdateIcons(list map[string]string) {
	n.mu.Lock()
	n.iconList = list
	n.mu.Unlock()
}

// Clean strips ignore-chars, applies channel mappings, and returns the
// canonical upper-case channel name.
func (n *Normaliser) Clean(channel string) string {
	if channel == "" {
		return ""
	}

	normalized := n.stripIgnoreChars(channel)

	// Channel mappings (regex or literal)
	for replace, search := range n.mappings {
		if strings.HasPrefix(search, "regex:") {
			// Regex mappings are not applied here to avoid importing regexp in
			// a hot path; callers that need regex should use the service layer.
			continue
		}
		parts := strings.Split(search, ",")
		for _, p := range parts {
			p = strings.TrimSpace(p)
			if strings.EqualFold(normalized, n.stripIgnoreChars(p)) {
				return strings.ToUpper(replace)
			}
		}
	}

	return strings.ToUpper(normalized)
}

func (n *Normaliser) stripIgnoreChars(s string) string {
	for _, c := range n.ignoreChars {
		s = strings.ReplaceAll(s, c, "")
	}
	return s
}

// IconURL performs a three-pass fuzzy match against the icon list:
//  1. Exact match
//  2. Forward: channelName contains candidate
//  3. Reverse: candidate contains channelName
//
// Returns empty string if nothing matches and no default is set.
func (n *Normaliser) IconURL(candidates []string) string {
	n.mu.RLock()
	list := n.iconList
	n.mu.RUnlock()

	for _, orig := range candidates {
		// Pass 1 – exact
		if url, ok := list[orig]; ok {
			return url
		}

		// Pass 2 – forward fuzzy (list entry contains orig)
		var best string
		bestLen := -1
		for name, url := range list {
			if containsFold(name, orig) {
				l := len([]rune(name))
				if bestLen < 0 || l < bestLen {
					bestLen = l
					best = url
				}
			}
		}
		if best != "" {
			return best
		}

		// Pass 3 – reverse fuzzy (orig contains list entry)
		bestLen = -1
		best = ""
		for name, url := range list {
			if containsFold(orig, name) {
				l := len([]rune(name))
				if l > bestLen {
					bestLen = l
					best = url
				}
			}
		}
		if best != "" {
			return best
		}
	}

	return n.defaultIcon
}

// containsFold is a case-insensitive substring check.
func containsFold(s, sub string) bool {
	s = strings.Map(unicode.ToLower, s)
	sub = strings.Map(unicode.ToLower, sub)
	return strings.Contains(s, sub)
}
