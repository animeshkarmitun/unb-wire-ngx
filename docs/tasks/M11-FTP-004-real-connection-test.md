# Task: M11-FTP-004 — Real FTP connection test in DeliverySettings

**Status:** ⏳ Pending
**Dependencies:** M11-FTP-001
**Parent ADR:** `app-data/delivery-settings.html` Card 1 Test Connection button

---

## 1. Contract (What)
- **Inputs:** Client's SFTP credentials from `client_channels.config` (host, port, username, password/key)
- **Outputs:**
  - Success: `testBtnText = '✓ Connection OK'`, toast with latency, update `last_success_at`
  - Failure: `testBtnText = '✗ Failed'`, toast with error message (connection refused, auth failed, timeout)
- **Authorization:** `distribution.edit` RBAC permission

---

## 2. Logic (How)
1. Update `DeliverySettings::testConnection()`:
   - Get active FTP channel for selected client.
   - Use `FtpDiskFactory::make($channel)` to create a live disk.
   - Attempt `$disk->listContents('/')` or `$disk->write('.unb-test', 'ok')` + delete.
   - Measure round-trip latency.
   - On success: update channel health, dispatch success toast with latency.
   - On failure: catch `\League\Flysystem\UnableToWriteFile`, SSH2 exceptions → dispatch error toast.
2. Update `ClientService::testFtpConnection()` to actually test (replace the stub).
3. Add timeout (5 seconds) to prevent hanging on unreachable hosts.

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Livewire/Admin/DeliverySettings.php` (`testConnection()` lines 244–248)
  - `app/Services/ClientService.php` (`testFtpConnection()` lines 323–338)
- **Reference:**
  - `app/Services/Delivery/FtpDiskFactory.php` (M11-FTP-001)
- **Tests:**
  - `tests/Feature/FtpConnectionTest.php` — mock adapter for success/failure, assert toast dispatch and channel health update

---

## 4. Prompt (For the Coding AI)
> Implement M11-FTP-004. Replace the fake `testConnection()` in DeliverySettings and `testFtpConnection()` in ClientService with real SFTP connection tests using `FtpDiskFactory`. Handle success (latency toast, channel health update) and failure (error toast, descriptive message). Add 5s timeout. Write tests with mocked Flysystem adapter.

---

## 5. Test Criteria
- [ ] Successful connection test updates `last_success_at` and resets `failure_count`
- [ ] Failed connection dispatches error toast with descriptive message
- [ ] Timeout after 5 seconds on unreachable host
- [ ] `DeliverySettingsTest` passes with 0 regressions
- [ ] `php -l` clean

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
