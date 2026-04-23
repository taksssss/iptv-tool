# IPTV Tool — Go Service

High-performance Go rewrite of the [taksssss/iptv-tool](https://github.com/taksssss/iptv-tool) PHP backend.

## Directory structure

```
go-service/
├── cmd/server/main.go          # entry point
├── internal/
│   ├── handler/                # HTTP layer (Gin handlers)
│   │   ├── epg.go              #   GET /?ch= / ?channel= (DIYP / LoveTV)
│   │   ├── playlist.go         #   GET /playlist.m3u, /epg.xml, /t.xml.gz
│   │   ├── proxy.go            #   GET /proxy?url=
│   │   └── icon.go             #   GET /?ch=&type=icon
│   ├── service/                # business logic
│   │   ├── epg.go              #   cache + DB query + response building
│   │   └── channel.go          #   channel list management
│   ├── repository/             # database access
│   │   ├── db.go               #   Open() + Migrate()
│   │   ├── epg.go              #   EPG queries
│   │   └── channel.go          #   channel queries
│   ├── model/                  # data structures
│   │   ├── epg.go
│   │   └── channel.go
│   ├── middleware/
│   │   └── auth.go             # token / UA / IP auth + rate limiter
│   ├── stream/
│   │   └── proxy.go            # zero-copy stream proxy + AES URL crypto
│   ├── playlist/
│   │   ├── m3u.go              # M3U / TXT generation
│   │   └── xmltv.go            # XMLTV generation
│   └── epg/
│       └── normaliser.go       # channel name cleaning + icon fuzzy match
├── pkg/
│   ├── config/config.go        # env-based configuration
│   ├── logger/logger.go        # slog JSON logger
│   ├── cache/cache.go          # Redis / memory / noop cache
│   └── httpclient/client.go    # shared HTTP client with connection pool
├── .env.example
├── Dockerfile
├── go.mod
└── nginx-split.conf            # Strangler-pattern traffic split
```

## Quick start

```bash
# 1. Copy environment config
cp .env.example .env
# edit .env as needed

# 2. (Optional) point DATA_DIR at your existing PHP data directory
export DATA_DIR=/path/to/htdocs/data

# 3. Run
go run ./cmd/server
```

## Docker

```bash
docker build -t iptv-go .
docker run -d \
  --name iptv-go \
  -p 8080:8080 \
  -v $HOME/epg:/app/data \
  -e PROXY_TOKEN=your_php_token \
  -e SERVER_URL=http://your-server:8080 \
  iptv-go
```

## API endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/?ch=CCTV1` | DIYP/百川 EPG |
| GET | `/?channel=CCTV1` | 超级直播 EPG |
| GET | `/?ch=CCTV1&type=icon` | Channel logo redirect |
| GET | `/epg.xml` | XMLTV (uncompressed) |
| GET | `/epg.xml.gz` | XMLTV (gzip) |
| GET | `/playlist.m3u` | M3U playlist |
| GET | `/playlist.txt` | TXT playlist |
| GET | `/proxy?url=<enc>` | Stream proxy |
| GET | `/healthz` | Health check |

All endpoints accept `?token=` for authentication.

## Strangler migration

Use `nginx-split.conf` to gradually move traffic from PHP to Go:

1. **Phase 1** – Go handles `/proxy`
2. **Phase 2** – Go handles `/playlist.m3u`, `/playlist.txt`
3. **Phase 3** – Go handles `/epg.xml`, `/t.xml.gz`
4. **Phase 4** – Remove PHP backend entirely

Both services share the same SQLite/MySQL database and data directory.

## Performance notes

- Single shared `*http.Client` with `MaxIdleConns=200 / MaxIdleConnsPerHost=20`
- `io.Copy` zero-copy for TS streams; buffered only for m3u8 manifest rewriting
- Redis cache with 24 h TTL for EPG responses; 10 min TTL for playlists
- Per-IP token-bucket rate limiter in `middleware.RateLimiter`
- Context cancellation: client disconnect immediately aborts upstream read

## Running tests

```bash
go test ./...
```
