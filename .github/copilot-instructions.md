# MyImouto Copilot Instructions

You are working in a legacy PHP application (custom RailsPHP-style framework), targeting PHP 8.5.

## Review Priorities
- Prioritize correctness, security, and backward compatibility over style-only changes.
- Prefer minimal, focused patches; avoid broad refactors unless explicitly requested.
- For pull request reviews, classify findings by severity: Critical, High, Medium, Low.
- Report only actionable findings with concrete file/line context.
- Do not suggest framework migrations or architecture rewrites unless the PR scope explicitly asks for it.

## Security Expectations
- Check authentication and authorization boundaries on all state-changing endpoints.
- Check CSRF protections for POST/PUT/DELETE-like actions.
- Validate user input and encoding/escaping paths to prevent XSS/SQLi/TypeErrors.
- For URL/file ingestion flows, evaluate SSRF and filesystem safety (path traversal, permissions, cleanup).

## Reliability Expectations
- Flag race conditions around create/approve/reject/delete state transitions.
- Prefer transaction + locking patterns where concurrent updates are possible.
- Highlight file/DB consistency risks and missing rollback/compensation behavior.

## Repository Conventions
- Keep compatibility with existing naming/style in this codebase.
- Prefer existing CI entrypoints and scripts when suggesting validation steps:
  - `composer run ci:lint`
  - `composer run test`
  - `composer run analyse`
  - `composer run cs-check`
