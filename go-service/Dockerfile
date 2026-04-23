# ---- Build stage -----------------------------------------------------------
FROM golang:1.22-alpine AS builder

RUN apk add --no-cache gcc musl-dev sqlite-dev

WORKDIR /src
COPY go.mod go.sum ./
RUN go mod download

COPY . .
RUN CGO_ENABLED=1 GOOS=linux go build -ldflags="-s -w" -o /iptv-service ./cmd/server

# ---- Runtime stage ---------------------------------------------------------
FROM alpine:3.20

RUN apk add --no-cache ca-certificates tzdata

WORKDIR /app
COPY --from=builder /iptv-service /app/iptv-service

# Data directory is expected to be mounted from the host
VOLUME ["/app/data"]

ENV LISTEN_ADDR=:8080 \
    DB_TYPE=sqlite \
    DB_DSN=/app/data/data.db \
    DATA_DIR=/app/data \
    CACHE_TYPE=memory \
    LOG_LEVEL=info \
    TZ=Asia/Shanghai

EXPOSE 8080
ENTRYPOINT ["/app/iptv-service"]
