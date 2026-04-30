package handler

import (
	"net/http"
	"time"

	"github.com/gin-gonic/gin"
	"github.com/taksssss/iptv-tool/go-service/internal/service"
)

// EPGHandler serves EPG data in DIYP/百川 and 超级直播 formats.
type EPGHandler struct {
	svc *service.EPGService
}

// NewEPGHandler constructs an EPGHandler.
func NewEPGHandler(svc *service.EPGService) *EPGHandler {
	return &EPGHandler{svc: svc}
}

// QueryDIYP handles GET /?ch=<channel>&date=<date>
func (h *EPGHandler) QueryDIYP(c *gin.Context) {
	ch := c.Query("ch")
	if ch == "" {
		c.Status(http.StatusBadRequest)
		return
	}
	date := normaliseDate(c.Query("date"))

	data, err := h.svc.QueryDIYP(c.Request.Context(), ch, date)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}

	c.Header("Access-Control-Allow-Origin", "*")
	c.Data(http.StatusOK, "application/json; charset=utf-8", data)
}

// QueryLoveTV handles GET /?channel=<channel>&date=<date>
func (h *EPGHandler) QueryLoveTV(c *gin.Context) {
	ch := c.Query("channel")
	if ch == "" {
		c.Status(http.StatusBadRequest)
		return
	}
	date := normaliseDate(c.Query("date"))

	data, err := h.svc.QueryLoveTV(c.Request.Context(), ch, date)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}

	c.Header("Access-Control-Allow-Origin", "*")
	c.Data(http.StatusOK, "application/json; charset=utf-8", data)
}

// normaliseDate converts various date formats to YYYY-MM-DD.
// Accepts: YYYYMMDD, YYYY-MM-DD, empty (→ today).
func normaliseDate(s string) string {
	// Strip non-digits
	digits := make([]byte, 0, 8)
	for i := 0; i < len(s) && len(digits) < 8; i++ {
		if s[i] >= '0' && s[i] <= '9' {
			digits = append(digits, s[i])
		}
	}
	if len(digits) == 8 {
		return string(digits[0:4]) + "-" + string(digits[4:6]) + "-" + string(digits[6:8])
	}
	return time.Now().Format("2006-01-02")
}
