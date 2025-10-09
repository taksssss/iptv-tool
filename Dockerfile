# Build stage
FROM golang:1.24-alpine AS builder

WORKDIR /build

# Install build dependencies
RUN apk add --no-cache gcc musl-dev

# Copy go mod files
COPY go.mod go.sum ./
RUN go mod download

# Copy source code
COPY . .

# Build the application
RUN CGO_ENABLED=1 GOOS=linux go build -a -installsuffix cgo -o iptv-tool main.go

# Runtime stage
FROM alpine:3.20

LABEL maintainer="tak"
LABEL description="Alpine based IPTV Tool with Go backend"

# Install runtime dependencies
RUN apk --no-cache --update add \
    tzdata \
    ca-certificates \
    && mkdir -p /app/epg/data

WORKDIR /app

# Copy binary from builder
COPY --from=builder /build/iptv-tool /app/

# Copy static assets
COPY epg/assets /app/epg/assets/

# Set timezone
ENV TZ=Asia/Shanghai

EXPOSE 80

ENTRYPOINT ["/app/iptv-tool", "-data", "/app/epg/data", "-port", "80"]
