package stream_test

import (
	"testing"

	"github.com/taksssss/iptv-tool/go-service/internal/stream"
)

func TestEncryptDecryptURL(t *testing.T) {
	cases := []struct {
		token string
		url   string
	}{
		{"mysecret", "http://example.com/live/cctv1.m3u8"},
		{"", "http://plain.example.com/stream.ts"},
		{"complexT0ken!@#", "https://user:pass@host/path?q=1&r=2"},
	}

	for _, tc := range cases {
		enc := stream.EncryptURL(tc.url, tc.token)
		got, err := stream.DecryptURL(enc, tc.token)
		if err != nil {
			t.Errorf("token=%q url=%q: decrypt error: %v", tc.token, tc.url, err)
			continue
		}
		if got != tc.url {
			t.Errorf("token=%q: want %q, got %q", tc.token, tc.url, got)
		}
	}
}

func TestDecryptInvalidInput(t *testing.T) {
	_, err := stream.DecryptURL("not-base64!!!", "token")
	if err == nil {
		t.Error("expected error for invalid base64")
	}
}
