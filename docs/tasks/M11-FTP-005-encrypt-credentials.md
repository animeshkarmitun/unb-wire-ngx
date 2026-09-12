# Task: M11-FTP-005 — Encrypt FTP/SFTP credentials at rest

**Status:** ⏳ Pending
**Dependencies:** M11-FTP-001
**Parent ADR:** NFR §8 Security, AGENTS.md §8 Security

---

## 1. Contract (What)
- **Inputs:** FTP/SFTP password or SSH private key from admin form
- **Outputs:** Encrypted credential stored in `client_channels.config` JSON, decrypted at runtime by `FtpDiskFactory`
- **Current state:** Passwords stored in **plaintext** in `config.password` JSON field

---

## 2. Logic (How)
1. Use Laravel's `Crypt::encryptString()` / `Crypt::decryptString()` (AES-256-CBC with app key).
2. Update `ClientService::saveFtpCredentials()`: encrypt password before storing.
3. Update `ClientService::onboardClient()`: encrypt default password in FTP channel config.
4. Update `FtpDiskFactory::make()`: decrypt password at runtime before building disk.
5. Update `DeliverySettings::saveCredentials()`: encrypt before save.
6. Migration: add a one-time artisan command `delivery:encrypt-credentials` to encrypt existing plaintext passwords (idempotent — skip already-encrypted values).

---

## 3. Context (Where)
- **Files to Modify:**
  - `app/Services/ClientService.php` (`saveFtpCredentials`, `onboardClient`)
  - `app/Services/Delivery/FtpDiskFactory.php` (decrypt on read)
  - `app/Livewire/Admin/DeliverySettings.php` (`saveCredentials`)
- **Files to Create:**
  - `app/Console/Commands/EncryptFtpCredentials.php` (one-time migration command)
- **Tests:**
  - `tests/Feature/FtpCredentialEncryptionTest.php` — roundtrip encrypt/decrypt, verify no plaintext in DB

---

## 4. Prompt (For the Coding AI)
> Implement M11-FTP-005. Use `Crypt::encryptString()` to encrypt FTP/SFTP passwords before storing in `client_channels.config`. Update `FtpDiskFactory` to decrypt at runtime. Update `ClientService` and `DeliverySettings` to encrypt on save. Create `delivery:encrypt-credentials` command for migrating existing plaintext passwords. Write tests verifying roundtrip and no plaintext storage.

---

## 5. Test Criteria
- [ ] New FTP credentials are encrypted in `client_channels.config.password`
- [ ] `FtpDiskFactory` successfully decrypts and connects
- [ ] `delivery:encrypt-credentials` command encrypts existing plaintext entries
- [ ] Plaintext password never appears in DB after save
- [ ] Existing `DeliverySettingsTest` passes
- [ ] `php -l` clean

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
