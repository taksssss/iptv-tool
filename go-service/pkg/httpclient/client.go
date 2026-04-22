// Package httpclient provides a pre-configured *http.Client with connection
// pooling suitable for high-concurrency upstream proxy requests.
package httpclient

import (
	"crypto/tls"
	"net"
	"net/http"
	"time"
)

// Options for building a shared HTTP client.
type Options struct {
	// DialTimeout is the TCP connect timeout. Default: 10s
	DialTimeout time.Duration
	// ResponseHeaderTimeout is the time to wait for the first response byte.
	ResponseHeaderTimeout time.Duration
	// MaxIdleConns is the global connection pool size. Default: 200
	MaxIdleConns int
	// MaxIdleConnsPerHost is per-host pool size. Default: 20
	MaxIdleConnsPerHost int
	// MaxConnsPerHost limits simultaneous connections to a host. 0 = unlimited.
	MaxConnsPerHost int
	// IdleConnTimeout is how long idle connections live. Default: 90s
	IdleConnTimeout time.Duration
	// DisableTLSVerify skips certificate validation (for self-signed upstreams).
	DisableTLSVerify bool
}

// DefaultOptions returns production-ready defaults.
func DefaultOptions() Options {
	return Options{
		DialTimeout:           10 * time.Second,
		ResponseHeaderTimeout: 15 * time.Second,
		MaxIdleConns:          200,
		MaxIdleConnsPerHost:   20,
		MaxConnsPerHost:       0,
		IdleConnTimeout:       90 * time.Second,
		DisableTLSVerify:      false,
	}
}

// New creates a *http.Client with the given Options.
func New(opts Options) *http.Client {
	if opts.DialTimeout == 0 {
		opts.DialTimeout = 10 * time.Second
	}
	if opts.MaxIdleConns == 0 {
		opts.MaxIdleConns = 200
	}
	if opts.MaxIdleConnsPerHost == 0 {
		opts.MaxIdleConnsPerHost = 20
	}
	if opts.IdleConnTimeout == 0 {
		opts.IdleConnTimeout = 90 * time.Second
	}

	transport := &http.Transport{
		DialContext: (&net.Dialer{
			Timeout:   opts.DialTimeout,
			KeepAlive: 30 * time.Second,
		}).DialContext,
		TLSClientConfig:       &tls.Config{InsecureSkipVerify: opts.DisableTLSVerify}, //nolint:gosec // configurable by operator
		ForceAttemptHTTP2:     true,
		MaxIdleConns:          opts.MaxIdleConns,
		MaxIdleConnsPerHost:   opts.MaxIdleConnsPerHost,
		MaxConnsPerHost:       opts.MaxConnsPerHost,
		IdleConnTimeout:       opts.IdleConnTimeout,
		TLSHandshakeTimeout:   10 * time.Second,
		ExpectContinueTimeout: 1 * time.Second,
		ResponseHeaderTimeout: opts.ResponseHeaderTimeout,
	}

	return &http.Client{
		Transport: transport,
		CheckRedirect: func(req *http.Request, via []*http.Request) error {
			if len(via) >= 10 {
				return http.ErrUseLastResponse
			}
			return nil
		},
	}
}
