# Attendance Presence Time Logic

This document describes the time sheet calculations and absence rules used throughout the HRMS application.

## Field Mappings

The following SpeedUP (gcRaporu) fields are mapped to the HRMS Time Sheet calculations:

| SpeedUP Field | HRMS Display | Purpose |
|---|---|---|
| `harekettarihi` | Attendance Date | The day of the attendance record |
| `gunlukdurum` | Day Status | Classification of the day (e.g., "HAFTA TATİLİ", "GENEL ÇALIŞMA") |
| `giris` | Entrance Time | Employee check-in time |
| `cikis` | Exit Time | Employee check-out time |
| `igecgelme` | Late Entrance (min) | **Primary** integer late arrival in minutes |
| `gecgelme` | Late Entrance (alt) | Fallback time-format late arrival |
| `ierkencikma` | Early Exit (min) | **Primary** integer early exit in minutes |
| `erkencikma` | Early Exit (alt) | Fallback time-format early exit |
| `devamsizlik` | Absence Flag | Boolean absence indicator |
| `gunlukdurum` | Holiday Status | Special day classification |

## Absence Rules

### Holiday / Weekend Exclusion

Days where `gunlukdurum` contains **"HAFTA TATİLİ"** (week holiday) are **excluded** from absence calculations:
- The day is **not counted** as an absence, even if no check-in/check-out exists
- Late minutes are set to **0**
- Early exit minutes are set to **0**
- Work time lack is set to **0**

**Implementation**: [TimeSheetReport::isWeekendHolidayStatus()](../app/Helpers/TimeSheetReport.php)

### Regular Absence Detection

For working days (`gunlukdurum` != "HAFTA TATİLİ"):
- A day is marked **absent** if:
  - `devamsizlik` field is true/evet/yes, **OR**
  - Both `giris` (check-in) and `cikis` (check-out) are empty/null

## Late Arrival & Early Exit Calculation

### Field Priority

The system prioritizes **integer fields** for accuracy:

1. **Late Arrival** (order of priority):
   - `igecgelme` (integer value) — used first if present
   - `geckelme` (time format, e.g., "00:30") — fallback
   - Converted to minutes and capped at 0 minimum

2. **Early Exit** (order of priority):
   - `ierkencikma` (integer value) — used first if present
   - `erkencikma` (time format, e.g., "00:20") — fallback
   - Converted to minutes and capped at 0 minimum

**Rationale**: Integer fields are more reliable than time-format strings for calculation.

**Implementation**: [TimeSheetReport::resolveColumn()](../app/Helpers/TimeSheetReport.php)

## Worked & Missing Minutes

### Worked Minutes

Calculated in order of preference:
1. Use `imeccalismasuresi`, `mec_calisma_suresi`, or similar **worked-minutes** column if available
2. If not present, compute from check-in/check-out times:
   - Parse `giris` (check-in) and `cikis` (check-out) as HH:MM
   - Calculate difference in minutes
   - Handle day wrap-around (if exit < entry, add 1 day)

**Implementation**: [TimeSheetReport::resolveWorkedMinutes()](../app/Helpers/TimeSheetReport.php)

### Missing Minutes (Work Time Lack)

Calculated as:
```
missing_minutes = max(required_minutes - worked_minutes, 0)
```

## Time Sheet UI & API Columns

### Daily Report Table

Displays per-employee, per-day:
- **Attendance Date**
- **Day Status** (new field showing `gunlukdurum` for holiday visibility)
- **Employee ID**, **Code**, **Name**
- **Entrance Time**, **Exit Time**
- **Late Entrance (min)** — from `igecgelme` or `geckelme`
- **Early Exit (min)** — from `ierkencikma` or `erkencikma`
- **Work Time Lack (min)**
- **Worked Time (min)**
- **Absent** — boolean badge (red = Yes, green = No)

### Summary Cards & Monthly Report

Aggregate counts:
- **Total Days** — all records (including holidays)
- **Late Entries** — count where `late_minutes > 0`
- **Early Exits** — count where `early_exit_minutes > 0`
- **Absences** — count where `is_absent = true` (excludes holidays)
- **Worked Hours** — total worked minutes formatted as HH:MM
- **Work Time Lack** — total missing minutes formatted as HH:MM

## API Endpoints

### `/api/timesheet/daily`

Returns paginated daily records with filtering.

**Query Parameters**:
- `date_from` — start date (YYYY-MM-DD)
- `date_to` — end date (YYYY-MM-DD)
- `search_term` — search by employee ID/code/name
- `per_page` — pagination size (default: 31)

**Response**:
```json
{
  "data": [ /* daily records */ ],
  "summary": {
    "total_days": 20,
    "late_entries": 3,
    "early_exits": 2,
    "absences": 1,
    "worked_hours": "160:00",
    "work_time_lack_hours": "8:00"
  },
  "links": { /* pagination */ }
}
```

### `/api/timesheet/monthly`

Returns monthly aggregate summaries.

**Response**:
```json
{
  "data": [
    {
      "month": "2026-06",
      "records": 20,
      "absences": 1,
      "late_entries": 3,
      "early_exits": 2,
      "worked_hours": "160:00",
      "work_time_lack_hours": "8:00"
    }
  ]
}
```

### `/api/timesheet/link-status`

Returns personnel import linkage statistics.

**Response**:
```json
{
  "ok": true,
  "key": "personelid + kurumkodu",
  "total_personnel_rows": 898,
  "linked_rows": 706207,
  "unlinked_rows": 190381
}
```

## Data Import

SpeedUP attendance data is imported into local MySQL tables:

- **Source**: SQL Server `SpeedUP.dbo.gcRaporu` + `SpeedUP.dbo.personel`
- **Destination**: 
  - `personnel_time_reports` (396,588 rows)
  - `personnel_employees` (898 rows)
- **Join Key**: `personelid` + `kurumkodu`

**Command**:
```bash
php artisan attendance:import-speedup \
  --host=192.168.1.12 \
  --port=6588 \
  --database=SpeedUP \
  --username=BiUser \
  --password="HRpass#987" \
  --chunk=50 \
  --truncate
```

**Implementation**: [ImportSpeedupAttendanceData](../app/Console/Commands/ImportSpeedupAttendanceData.php)

## Testing

Run targeted attendance tests:
```bash
php artisan test tests/Feature/TimeSheetApiTest.php
```

- ✓ Holiday exclusion from absence counts
- ✓ Integer late/early fields take priority
- ✓ Weekend holidays show zero penalties
- ✓ API returns correct summary metrics
