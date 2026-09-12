# Task: M11-FTP-001 — Install flysystem-sftp-v3 + dynamic disk factory

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** FR-DST-002, DEC-005

---

## 1. Contract (What)
- **Inputs:** `ClientChannel` with `type = 'ftp'`, `config` containing `{host, port, username, password, auth_type}`
- **Outputs:** A configured `Filesystem` disk instance capable of writing files to the client's SFTP/FTP server
- **Package:** `league/flysystem-sftp-v3` (SFTP via SSH2) — FTP falls back to standard Flysystem FTP adapter

---

## 2. Logic (How)
1. `composer require league/flysystem-sftp-v3` (adds SSH2/SFTP support).
2. Create `App\Services\Delivery\FtpDiskFactory` with:
   - `make(ClientChannel $channel): \Illuminate\Filesystem\FilesystemAdapter` — builds a Flysystem disk dynamically from channel config.
   - Supports SFTP (SSH key or password auth) and plain FTP based on `config.auth_type`.
   - Does NOT register static disks in `config/filesystems.php` — each client has different credentials.
3. Credentials are decrypted at runtime (see M11-FTP-005 for encryption).

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Services/Delivery/FtpDiskFactory.php`
- **Files to Modify:**
  - `composer.json` (add `league/flysystem-sftp-v3`)
- **Reference:**
  - `config/filesystems.php` (existing S3 disk for pattern reference)
  - `app/Models/ClientChannel.php` (config structure)
  - `app/Services/ClientService.php` lines 340–368 (`saveFtpCredentials`)
- **Tests:**
  - `tests/Feature/FtpDiskFactoryTest.php` — mock SFTP adapter creation, verify config mapping

---

## 4. Prompt (For the Coding AI)
> Implement M11-FTP-001. Run `composer require league/flysystem-sftp-v3`. Create `FtpDiskFactory` service that builds a Flysystem disk from `ClientChannel` config. Support SFTP (password + SSH key) and FTP. Write tests verifying disk creation with various config combinations. Do NOT modify `config/filesystems.php` — disks are dynamic per-client.

---

## 5. Test Criteria
- [ ] `composer require` succeeds, `composer.json` updated
- [ ] `FtpDiskFactory::make()` returns a valid `FilesystemAdapter`
- [ ] SFTP config maps correctly (host, port, username, password/privateKey)
- [ ] FTP config maps correctly (host, port, username, password, passive mode)
- [ ] Invalid config throws descriptive exception
- [ ] `php -l` clean, `php artisan test` passes

---

## 6. Completion Notes
- Required `league/flysystem-sftp-v3` and `league/flysystem-ftp` using composer.
- Implemented `FtpDiskFactory` with dynamic SFTP and FTP connection mapping.
- Added `FtpDiskFactoryTest` to ensure configurations map to the correct connection adapters.

## 7. Prompt Ready?
- [x] Yes

