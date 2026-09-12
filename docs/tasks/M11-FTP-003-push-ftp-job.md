# Task: M11-FTP-003 — PushFtpDeliveryJob (queue job for FTP push)

**Status:** ⏳ Pending
**Dependencies:** M11-FTP-001, M11-FTP-002
**Parent ADR:** FR-DST-002, DEC-005

---

## 1. Contract (What)
- **Inputs:** `Delivery` record with `channel.type = 'ftp'`, `deliverable_type = 'story'`
- **Outputs:** File uploaded to client SFTP/FTP server → `delivery.status = 'sent'`
- **Queue:** `fanout` queue, tries = 3, backoff = 120s

---

## 2. Logic (How)
1. Create `App\Jobs\PushFtpDelivery` job class.
2. `handle()`:
   - Load `Delivery` with `channel` and `deliverable` (Story).
   - Get wire format from `channel.config.wire_format` (default `json-unb-v1`).
   - Generate formatted file via `WireFormatFactory::generate($story, $format)`.
   - Build SFTP disk via `FtpDiskFactory::make($channel)`.
   - Upload file: `$disk->put($wireOutput->filename, $wireOutput->content)`.
   - If media attachments included in format and `channel.config.push_media`:
     - Download each media derivative from S3, upload to client SFTP.
   - On success: update `delivery.status = 'sent'`, `sent_at`, call `recordChannelSuccess()`.
   - On failure: increment `attempt_count`, set `error`, call `recordChannelFailure()`.
3. Update `FanoutStory.php`: when `channel.type === 'ftp'`, dispatch `PushFtpDelivery::dispatch($delivery)` instead of doing nothing.
4. Integrate with `ProcessDeliveriesCommand` (M11-HOOK-004) for retry of failed FTP deliveries.

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Jobs/PushFtpDelivery.php`
- **Files to Modify:**
  - `app/Jobs/FanoutStory.php` (dispatch `PushFtpDelivery` for ftp channels)
- **Reference:**
  - `app/Services/Delivery/FtpDiskFactory.php` (M11-FTP-001)
  - `app/Services/Delivery/WireFormatFactory.php` (M11-FTP-002)
  - `app/Repositories/ClientRepository.php` (recordChannelSuccess/Failure)
- **Tests:**
  - `tests/Feature/PushFtpDeliveryTest.php` — mock Flysystem, assert file written, status transitions

---

## 4. Prompt (For the Coding AI)
> Implement M11-FTP-003. Create `PushFtpDelivery` queue job. On handle: load story, generate wire format, create SFTP disk, upload file, update delivery status. Update `FanoutStory` to dispatch this job for FTP channels. Write tests with mocked Flysystem verifying file upload, success/failure status, and channel health recording.

---

## 5. Test Criteria
- [ ] FTP delivery job generates correct wire format file
- [ ] File is uploaded to mocked SFTP disk with correct filename
- [ ] `delivery.status` transitions to `sent` on success
- [ ] Failure increments `attempt_count` and records error
- [ ] `FanoutStory` dispatches `PushFtpDelivery` for ftp channels (via `Queue::fake()`)
- [ ] `php -l` clean, existing tests pass

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
