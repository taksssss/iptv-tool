// Package cache provides a unified caching abstraction over Redis and an
// in-process memory store. Both implementations satisfy the Cache interface
// so the rest of the application remains decoupled from the backend.
package cache

import (
	"context"
	"sync"
	"time"

	"github.com/redis/go-redis/v9"
)

// Cache is the minimal interface used throughout the service.
type Cache interface {
	Get(ctx context.Context, key string) (string, bool)
	Set(ctx context.Context, key, value string, ttl time.Duration) error
	Del(ctx context.Context, keys ...string) error
	Flush(ctx context.Context) error
}

// ---- Redis implementation ------------------------------------------------

// RedisCache wraps go-redis with the Cache interface.
type RedisCache struct {
	client *redis.Client
}

// NewRedis constructs a RedisCache and verifies the connection.
func NewRedis(addr, password string, db int) (*RedisCache, error) {
	rdb := redis.NewClient(&redis.Options{
		Addr:         addr,
		Password:     password,
		DB:           db,
		DialTimeout:  3 * time.Second,
		ReadTimeout:  3 * time.Second,
		WriteTimeout: 3 * time.Second,
		PoolSize:     20,
		MinIdleConns: 5,
	})
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()
	if err := rdb.Ping(ctx).Err(); err != nil {
		return nil, err
	}
	return &RedisCache{client: rdb}, nil
}

func (r *RedisCache) Get(ctx context.Context, key string) (string, bool) {
	val, err := r.client.Get(ctx, key).Result()
	if err != nil {
		return "", false
	}
	return val, true
}

func (r *RedisCache) Set(ctx context.Context, key, value string, ttl time.Duration) error {
	return r.client.Set(ctx, key, value, ttl).Err()
}

func (r *RedisCache) Del(ctx context.Context, keys ...string) error {
	return r.client.Del(ctx, keys...).Err()
}

func (r *RedisCache) Flush(ctx context.Context) error {
	return r.client.FlushDB(ctx).Err()
}

// ---- In-memory implementation -------------------------------------------

type memEntry struct {
	value     string
	expiresAt time.Time
}

// MemoryCache is a simple thread-safe in-process cache.
type MemoryCache struct {
	mu    sync.RWMutex
	items map[string]memEntry
}

// NewMemory creates an in-process cache.
func NewMemory() *MemoryCache {
	c := &MemoryCache{items: make(map[string]memEntry)}
	go c.evict()
	return c
}

func (m *MemoryCache) Get(_ context.Context, key string) (string, bool) {
	m.mu.RLock()
	e, ok := m.items[key]
	m.mu.RUnlock()
	if !ok || time.Now().After(e.expiresAt) {
		return "", false
	}
	return e.value, true
}

func (m *MemoryCache) Set(_ context.Context, key, value string, ttl time.Duration) error {
	m.mu.Lock()
	m.items[key] = memEntry{value: value, expiresAt: time.Now().Add(ttl)}
	m.mu.Unlock()
	return nil
}

func (m *MemoryCache) Del(_ context.Context, keys ...string) error {
	m.mu.Lock()
	for _, k := range keys {
		delete(m.items, k)
	}
	m.mu.Unlock()
	return nil
}

func (m *MemoryCache) Flush(_ context.Context) error {
	m.mu.Lock()
	m.items = make(map[string]memEntry)
	m.mu.Unlock()
	return nil
}

// evict runs a background goroutine to remove expired entries.
func (m *MemoryCache) evict() {
	ticker := time.NewTicker(5 * time.Minute)
	defer ticker.Stop()
	for range ticker.C {
		now := time.Now()
		m.mu.Lock()
		for k, e := range m.items {
			if now.After(e.expiresAt) {
				delete(m.items, k)
			}
		}
		m.mu.Unlock()
	}
}

// ---- Noop implementation ------------------------------------------------

// NoopCache is a cache that never stores anything.
type NoopCache struct{}

func (NoopCache) Get(_ context.Context, _ string) (string, bool)            { return "", false }
func (NoopCache) Set(_ context.Context, _, _ string, _ time.Duration) error { return nil }
func (NoopCache) Del(_ context.Context, _ ...string) error                  { return nil }
func (NoopCache) Flush(_ context.Context) error                             { return nil }
