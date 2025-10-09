package cron

import (
	"fmt"
	"log"
	"time"

	"github.com/taksssss/iptv-tool/internal/config"
	"github.com/taksssss/iptv-tool/internal/database"
)

// Service represents the cron service
type Service struct {
	cfg      *config.Config
	db       *database.DB
	stopChan chan bool
}

// NewService creates a new cron service
func NewService(cfg *config.Config, db *database.DB) *Service {
	return &Service{
		cfg:      cfg,
		db:       db,
		stopChan: make(chan bool),
	}
}

// Start starts the cron service
func (s *Service) Start() error {
	if s.cfg.IntervalTime == 0 {
		log.Println("Cron service disabled (interval_time = 0)")
		return nil
	}

	if s.cfg.StartTime == "" || s.cfg.EndTime == "" {
		log.Println("Cron service disabled (missing start_time or end_time)")
		return nil
	}

	// Parse start and end times
	startParts := parseTime(s.cfg.StartTime)
	endParts := parseTime(s.cfg.EndTime)

	if startParts == nil || endParts == nil {
		return fmt.Errorf("invalid time format")
	}

	// Generate execution schedule
	schedule := s.generateSchedule(startParts, endParts, s.cfg.IntervalTime)

	// Log schedule
	s.logMessage(fmt.Sprintf("【开始时间】 %s", s.cfg.StartTime))
	s.logMessage(fmt.Sprintf("【结束时间】 %s", s.cfg.EndTime))
	scheduleMsg := fmt.Sprintf("【间隔时间】 %d分钟\n", s.cfg.IntervalTime/60)
	scheduleMsg += "\t\t\t\t-------运行时间表-------\n"
	for _, t := range schedule {
		scheduleMsg += fmt.Sprintf("\t\t\t\t\t      %02d:%02d\n", t.Hour, t.Minute)
	}
	scheduleMsg += "\t\t\t\t--------------------------"
	s.logMessage(scheduleMsg)

	// Calculate next execution time
	nextExec := s.getNextExecution(schedule)
	if nextExec != nil {
		s.logMessage(fmt.Sprintf("【下次执行】 %02d/%02d %02d:%02d", 
			nextExec.Month(), nextExec.Day(), nextExec.Hour(), nextExec.Minute()))
	}

	// Start the scheduler goroutine
	go s.run(schedule)

	return nil
}

// Stop stops the cron service
func (s *Service) Stop() {
	close(s.stopChan)
}

// run runs the cron scheduler
func (s *Service) run(schedule []TimePoint) {
	ticker := time.NewTicker(60 * time.Second)
	defer ticker.Stop()

	executeCount := 0

	for {
		select {
		case <-s.stopChan:
			return
		case <-ticker.C:
			now := time.Now()
			currentMinute := now.Hour()*60 + now.Minute()

			for _, t := range schedule {
				scheduleMinute := t.Hour*60 + t.Minute

				if currentMinute == scheduleMinute {
					// Execute update
					executeCount++
					s.logMessage(fmt.Sprintf("【成功执行】 update (%d)", executeCount))

					// TODO: Call update handler
					go s.executeUpdate()

					// Calculate next execution
					nextExec := s.getNextExecution(schedule)
					if nextExec != nil {
						s.logMessage(fmt.Sprintf("【下次执行】 %02d/%02d %02d:%02d",
							nextExec.Month(), nextExec.Day(), nextExec.Hour(), nextExec.Minute()))
					}

					// Check if should run speed check
					if s.cfg.CheckSpeedAutoSync == 1 && 
					   s.cfg.CheckSpeedIntervalFactor > 0 &&
					   executeCount % s.cfg.CheckSpeedIntervalFactor == 0 {
						s.logMessage(fmt.Sprintf("【测速校验】 已在后台运行 (%d)", 
							executeCount/s.cfg.CheckSpeedIntervalFactor))
						go s.executeSpeedCheck()
					}

					break
				}
			}
		}
	}
}

// TimePoint represents a time point in the schedule
type TimePoint struct {
	Hour   int
	Minute int
}

// generateSchedule generates the execution schedule
func (s *Service) generateSchedule(start, end []int, interval int) []TimePoint {
	var schedule []TimePoint

	startSec := start[0]*3600 + start[1]*60
	endSec := end[0]*3600 + end[1]*60

	// Handle cross-day scenarios
	if endSec <= startSec {
		endSec += 24 * 3600
	}

	for t := startSec; t <= endSec; t += interval {
		hour := (t / 3600) % 24
		minute := (t % 3600) / 60
		schedule = append(schedule, TimePoint{Hour: hour, Minute: minute})
	}

	return schedule
}

// getNextExecution calculates the next execution time
func (s *Service) getNextExecution(schedule []TimePoint) *time.Time {
	now := time.Now()
	currentMinute := now.Hour()*60 + now.Minute()

	for _, t := range schedule {
		scheduleMinute := t.Hour*60 + t.Minute
		if scheduleMinute > currentMinute {
			next := time.Date(now.Year(), now.Month(), now.Day(), t.Hour, t.Minute, 0, 0, now.Location())
			return &next
		}
	}

	// Next execution is tomorrow
	if len(schedule) > 0 {
		tomorrow := now.AddDate(0, 0, 1)
		next := time.Date(tomorrow.Year(), tomorrow.Month(), tomorrow.Day(), 
			schedule[0].Hour, schedule[0].Minute, 0, 0, now.Location())
		return &next
	}

	return nil
}

// logMessage logs a message to the database
func (s *Service) logMessage(message string) {
	log.Println(message)
	_, err := s.db.Exec("INSERT INTO cron_log (timestamp, log_message) VALUES (?, ?)",
		time.Now().Format("2006-01-02 15:04:05"), message)
	if err != nil {
		log.Printf("Failed to log message: %v", err)
	}
}

// executeUpdate executes the update process
func (s *Service) executeUpdate() {
	// TODO: Implement update execution
	log.Println("Executing update...")
}

// executeSpeedCheck executes the speed check process
func (s *Service) executeSpeedCheck() {
	// TODO: Implement speed check execution
	log.Println("Executing speed check...")
}

// parseTime parses a time string in HH:mm format
func parseTime(timeStr string) []int {
	var hour, minute int
	_, err := fmt.Sscanf(timeStr, "%d:%d", &hour, &minute)
	if err != nil {
		return nil
	}
	return []int{hour, minute}
}
