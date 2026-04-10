---
applyTo: "app/controllers/TicketController.php,app/controllers/TakedownController.php,app/models/Ticket.php,app/models/Takedown.php,app/models/TakedownPost.php,app/views/ticket/**/*.php,app/views/takedown/**/*.php"
---

Focus this area on moderation workflow integrity and access control.

- Verify ticket visibility rules are enforced consistently:
  - dmail-type tickets: visible only to creator and Mod+
  - post-type tickets: visible to creator and Janitor+
  - all other types: visible to creator and Mod+
  - Filters must apply at query level (WHERE clause), not post-load. show() must return 404 (not 403) for unauthorized access.
- Verify duplicate ticket detection:
  - Open tickets with same (creator_id, model_type, model_id) must be caught before creation.
  - Mod+ may bypass the duplicate check.
  - Only applies when both model_type and model_id are non-null.
- Verify optimistic locking on ticket claim:
  - claim() must use conditional UPDATE with WHERE claimant_id IS NULL.
  - Concurrent claim attempts must return HTTP 409 with current claimant info.
  - Do not use SELECT FOR UPDATE for this use case.
- Verify Dmail notification safety:
  - Dmail delivery failure must never prevent a ticket status transition.
  - Notifications only on terminal status changes (approved, rejected).
  - Check receive_ticket_dmails preference before sending.
  - Staff response excerpt only — no staff-internal notes in Dmail body.
- For takedown tag-based post selection:
  - Enforce the 1000-post safety cap.
  - Empty tag queries and zero-result queries must not create empty takedowns.
- Treat these as high-priority risks:
  - Visibility bypass via direct ticket ID access
  - Dmail exception blocking status transition
  - Claim race condition without atomic UPDATE
  - Tag query returning unbounded result sets
