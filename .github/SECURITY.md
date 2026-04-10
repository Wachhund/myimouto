# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| master (HEAD) | Yes |
| Older releases | No |

## Reporting a Vulnerability

**Do not open a public issue for security vulnerabilities.**

Please report security issues via one of these channels:

1. **GitHub Private Vulnerability Reporting**: Use the "Report a vulnerability" button on the [Security tab](../../security/advisories/new) of this repository.
2. **Email**: Send details to the repository owner (see GitHub profile).

### What to include

- Description of the vulnerability
- Steps to reproduce (if applicable)
- Affected files or endpoints
- Potential impact assessment

### Response expectations

- Acknowledgment within 72 hours
- Status update within 7 days
- Coordinated disclosure after fix is deployed

## Security Measures

This project has undergone multiple security hardening phases:

- **Authentication**: bcrypt password hashing, secure session tokens, cookie flags (PROJ-27)
- **CSRF**: Global CSRF protection with HTTP verb enforcement (PROJ-28)
- **Input Hardening**: SQL injection sweep, mass-assignment protection, SWF/htmLawed sanitization (PROJ-29)
- **Headers & Rate Limiting**: Security headers, login rate limiting (PROJ-30)
- **Audit Logging**: Structured ModAction logging for all moderation actions (PROJ-37)
