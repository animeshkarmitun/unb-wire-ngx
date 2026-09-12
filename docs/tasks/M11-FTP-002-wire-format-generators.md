# Task: M11-FTP-002 — Wire format generators (NewsML-G2, JSON, NITF)

**Status:** ⏳ Pending
**Dependencies:** None
**Parent ADR:** FR-DST-002, `app-data/delivery-settings.html` Card 1 wire format dropdown

---

## 1. Contract (What)
- **Inputs:** Published `Story` (with category, tags, media eager-loaded) + format type string
- **Outputs:** Formatted file content + filename:
  - `newsml-g2` → XML string conforming to NewsML-G2 2.32 schema, filename `UNB-{public_id}.xml`
  - `json-unb-v1` → JSON matching Portal API `story/{id}` shape, filename `UNB-{public_id}.json`
  - `nitf` → NITF XML, filename `UNB-{public_id}.nitf.xml`
- **Wire format dropdown values** (from `DeliverySettings` Card 1): `NewsML-G2 (XML)`, `NITF (XML)`, `JSON (UNB v1)`, `RSS 2.0`

---

## 2. Logic (How)
1. Create `App\Services\Delivery\WireFormatFactory` with `generate(Story $story, string $format): WireOutput`.
2. `WireOutput` is a simple DTO: `{content: string, filename: string, contentType: string}`.
3. Implement format strategies:
   - `NewsmlG2Formatter` — XML with `<newsItem>`, `<contentMeta>`, `<contentSet>`, `<inlineXML>` body.
   - `JsonUnbV1Formatter` — JSON matching existing PortalController::show() shape.
   - `NitfFormatter` — NITF XML with `<head>`, `<body>`, `<body.content>`.
4. RSS 2.0 deferred (not used for FTP push).

---

## 3. Context (Where)
- **Files to Create:**
  - `app/Services/Delivery/WireFormatFactory.php`
  - `app/Services/Delivery/Formats/NewsmlG2Formatter.php`
  - `app/Services/Delivery/Formats/JsonUnbV1Formatter.php`
  - `app/Services/Delivery/Formats/NitfFormatter.php`
  - `app/Services/Delivery/WireOutput.php` (DTO)
- **Reference:**
  - `app/Http/Controllers/Api/PortalController.php` (JSON story shape)
  - NewsML-G2 spec: https://iptc.org/standards/newsml-g2/
- **Tests:**
  - `tests/Feature/WireFormatTest.php` — assert valid XML/JSON for each format, filename conventions

---

## 4. Prompt (For the Coding AI)
> Implement M11-FTP-002. Create `WireFormatFactory` with format strategies for NewsML-G2, JSON UNB v1, and NITF. Each takes a Story and produces `WireOutput` (content + filename + contentType). JSON format should match the existing PortalController::show() shape. XML formats should produce valid, well-formed XML. Write tests asserting correct output for each format.

---

## 5. Test Criteria
- [ ] NewsML-G2 output is valid XML with `<newsItem>` root
- [ ] JSON UNB v1 output matches PortalController story shape
- [ ] NITF output is valid XML with `<nitf>` root
- [ ] Filenames follow `UNB-{public_id}.{ext}` convention
- [ ] Media references included in all formats
- [ ] `php -l` clean

---

## 6. Completion Notes
*(filled on completion)*

## 7. Prompt Ready?
- [x] Yes
