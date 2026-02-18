---
applyTo: "app/controllers/PostReplacementController.php,app/models/PostReplacement.php,app/models/Post.php,lib/MyImouto/PostReplacement/**/*.php,tests/Unit/PostReplacement/**/*.php"
---

Focus this area on moderation safety and consistency.

- Verify lifecycle integrity for replacement states (`pending`, `approved`, `rejected`, `deleted`).
- Verify authorization boundaries:
  - submit vs moderate permissions
  - self-cancel policy vs staff-only moderation flows
- Treat these as high-priority risks:
  - duplicate pending creation under concurrency
  - status transition races without row locks
  - network I/O inside long DB transactions
  - stale staged files on error paths
  - file/DB drift after partial replacement failures
- For URL-based replacements, check SSRF protections and unsafe address handling (private, loopback, mapped IPv6 cases).
- Distinguish policy suggestions from defects; do not mark policy alternatives as bugs.
