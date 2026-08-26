# Sudarshan Yuvak Mandal — System Design
## Free-Level Concepts for Fast Retrieval & Efficient Saving

---

## 1. Database Index Strategy

The most impactful free optimization. Run this once in phpMyAdmin:

```sql
-- Audit Logs: most queried by action + time (paginated desc)
ALTER TABLE audit_logs
  ADD INDEX idx_audit_created (created_at),
  ADD INDEX idx_audit_action  (action),
  ADD INDEX idx_audit_user    (user_id),
  ADD INDEX idx_audit_actor   (actor_id);

-- mandal_requests: filtered by status + type + date
ALTER TABLE mandal_requests
  ADD INDEX idx_req_status   (status),
  ADD INDEX idx_req_user     (user_id),
  ADD INDEX idx_req_type     (request_type),
  ADD INDEX idx_req_date     (event_date),
  ADD INDEX idx_req_hidden   (is_hidden);

-- otp_tokens: looked up by (email, purpose, is_used) on every OTP verify
ALTER TABLE otp_tokens
  ADD INDEX idx_otp_email_purpose (email, purpose, is_used),
  ADD INDEX idx_otp_expires       (expires_at);

-- rate_limits: looked up by (ip, email, action_type) on every request
ALTER TABLE rate_limits
  ADD INDEX idx_rl_lookup (ip_address, email, action_type);

-- users: fast role + status lookup
ALTER TABLE users
  ADD INDEX idx_user_role_status (role, membership_status),
  ADD INDEX idx_user_email       (email);
```

**Impact:** Turns full-table scans into index-range scans. For 50 members and thousands of audit logs this cuts query time by 10-100x.

---

## 2. OTP Token Auto-Cleanup (Scheduled Job)

Expired OTP tokens accumulate over time. Add a MySQL EVENT to auto-purge:

```sql
-- Enable event scheduler (run once)
SET GLOBAL event_scheduler = ON;

-- Delete expired + used OTP tokens older than 24 hours every hour
CREATE EVENT IF NOT EXISTS cleanup_otp_tokens
  ON SCHEDULE EVERY 1 HOUR
  DO DELETE FROM otp_tokens WHERE expires_at < NOW() - INTERVAL 24 HOUR;
```

**Without this:** The `otp_tokens` table grows unboundedly, slowing every OTP lookup.

---

## 3. Audit Log Archival (Partition Old Records)

As the audit log grows to thousands of rows, keep the live table small:

```sql
-- Archive logs older than 90 days
CREATE TABLE IF NOT EXISTS audit_logs_archive LIKE audit_logs;

-- Run monthly (or as a scheduled event)
INSERT INTO audit_logs_archive
  SELECT * FROM audit_logs WHERE created_at < NOW() - INTERVAL 90 DAY;

DELETE FROM audit_logs WHERE created_at < NOW() - INTERVAL 90 DAY;
```

**Pattern:** "Hot" table stays small (< 5,000 rows at 50 members). Admin views live table; archive available for compliance.

---

## 4. Session-Level Analytics Cache

`get_costing_analytics` queries `mandal_requests` with SUMs. With infrequent changes (requests go from pending → approved), this is safe to cache for 5 minutes in the session:

```php
// In request_handler.php, get_costing_analytics case:
$cacheKey = 'analytics_cache';
$cacheTTL = 300; // 5 minutes

if (isset($_SESSION[$cacheKey]) && (time() - $_SESSION[$cacheKey]['ts']) < $cacheTTL) {
    sendJsonResponse('success', 'Analytics loaded', ['analytics' => $_SESSION[$cacheKey]['data']]);
    break;
}
// ... run query ...
$_SESSION[$cacheKey] = ['data' => $analytics, 'ts' => time()];
```

**Invalidate on:** Any `approve_request` or `reject_request` action — add `unset($_SESSION['analytics_cache'])` to admin_handler.

---

## 5. Optimistic Locking (Already In Place ✅)

`approve_member` and `reactivate_member` use:
```sql
SELECT COUNT(*) FROM users WHERE membership_status = 'approved' FOR UPDATE
```
This prevents race conditions when two admins approve the 50th member simultaneously — the second request will correctly fail. **No change needed.**

---

## 6. PHP Opcode Cache (OPcache)

XAMPP ships with OPcache disabled by default. Enable it in `php.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

**Impact:** PHP files are compiled once per minute instead of on every request. Free 30-50% speed boost on all PHP pages.

---

## 7. Rate Limit Table Cleanup

`rate_limits` rows become stale. Add periodic cleanup:

```sql
CREATE EVENT IF NOT EXISTS cleanup_rate_limits
  ON SCHEDULE EVERY 1 HOUR
  DO DELETE FROM rate_limits WHERE window_start < NOW() - INTERVAL 2 HOUR;
```

---

## 8. Audit Log Query Optimization (Current Implementation)

The current paginated `get_audit_logs` uses:
```sql
ORDER BY a.id DESC LIMIT 50 OFFSET N
```

With the `idx_audit_created` index on `id` (auto-increment = chronological), this is already optimal.
For very large datasets (100k+ rows), consider **keyset pagination** instead:
```sql
WHERE a.id < :last_seen_id ORDER BY a.id DESC LIMIT 50
```

---

## Summary Table

| Concept | Effort | Impact | Status |
|---------|--------|--------|--------|
| DB Indexes | 1 SQL run | 🚀 Very High | **Run once in phpMyAdmin** |
| OTP Cleanup Event | 1 SQL run | High | **Run once in phpMyAdmin** |
| Audit Archival | Monthly cron | Medium | Manual / Scheduled |
| Analytics Session Cache | 10 min code | Medium | Optional enhancement |
| OPcache Enable | 2 lines php.ini | High | **Enable in XAMPP** |
| Rate Limit Cleanup | 1 SQL run | Medium | **Run once in phpMyAdmin** |
| Optimistic Locking | Already done | High | ✅ Done |
| Keyset Pagination | Future | Medium | Future if logs > 100k |
