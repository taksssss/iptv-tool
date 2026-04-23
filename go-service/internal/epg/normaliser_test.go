package epg_test

import (
	"testing"

	"github.com/taksssss/iptv-tool/go-service/internal/epg"
)

func TestNormaliserClean(t *testing.T) {
	norm := epg.NewNormaliser([]string{" ", "-"}, nil, nil, "")

	cases := []struct {
		input string
		want  string
	}{
		{"CCTV 1", "CCTV1"},
		{"cctv-1", "CCTV1"},
		{"湖南卫视", "湖南卫视"},
		{"", ""},
	}
	for _, tc := range cases {
		got := norm.Clean(tc.input)
		if got != tc.want {
			t.Errorf("Clean(%q) = %q, want %q", tc.input, got, tc.want)
		}
	}
}

func TestIconURLExactMatch(t *testing.T) {
	icons := map[string]string{
		"CCTV1":  "http://example.com/cctv1.png",
		"湖南卫视": "http://example.com/hunan.png",
	}
	norm := epg.NewNormaliser(nil, nil, icons, "http://default.png")

	if got := norm.IconURL([]string{"CCTV1"}); got != icons["CCTV1"] {
		t.Errorf("exact match: got %q, want %q", got, icons["CCTV1"])
	}
}

func TestIconURLFuzzyForward(t *testing.T) {
	icons := map[string]string{
		"CCTV1综合": "http://example.com/cctv1.png",
	}
	norm := epg.NewNormaliser(nil, nil, icons, "")

	// "CCTV1" is contained in "CCTV1综合" → forward fuzzy
	got := norm.IconURL([]string{"CCTV1"})
	if got != icons["CCTV1综合"] {
		t.Errorf("forward fuzzy: got %q, want %q", got, icons["CCTV1综合"])
	}
}

func TestIconURLFuzzyReverse(t *testing.T) {
	icons := map[string]string{
		"CCTV": "http://example.com/cctv.png",
	}
	norm := epg.NewNormaliser(nil, nil, icons, "")

	// "CCTV" is contained in "CCTV1" → reverse fuzzy
	got := norm.IconURL([]string{"CCTV1"})
	if got != icons["CCTV"] {
		t.Errorf("reverse fuzzy: got %q, want %q", got, icons["CCTV"])
	}
}

func TestIconURLDefault(t *testing.T) {
	defaultIcon := "http://default.example.com/icon.png"
	norm := epg.NewNormaliser(nil, nil, nil, defaultIcon)

	got := norm.IconURL([]string{"NoMatch"})
	if got != defaultIcon {
		t.Errorf("default icon: got %q, want %q", got, defaultIcon)
	}
}
