# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 0.6.x   | Yes       |
| < 0.6   | No        |

## Reporting a Vulnerability

If you discover a security vulnerability in AI Gateway, please report it responsibly.

**Email:** [the@duyet.dev](mailto:the@duyet.dev)

**Subject line:** AI Gateway Security

**Include in your report:**

- A description of the vulnerability
- Steps to reproduce the issue
- The potential impact (e.g., data exposure, privilege escalation)
- Any suggested fixes, if applicable

**Response time:** You can expect an initial acknowledgment within 48 hours. We aim to provide a resolution or mitigation plan within 7 days for critical issues.

## Disclosure Policy

We follow a coordinated disclosure process:

1. The vulnerability is reported privately via email
2. We confirm receipt and begin investigation
3. A fix is developed and tested
4. A patched version is released
5. The vulnerability is disclosed publicly after the fix is available

Please do not disclose the vulnerability publicly until a fix has been released.

## Scope

### In Scope

- Authentication bypass
- Privilege escalation (non-admin accessing admin-only endpoints)
- SQL injection
- Path traversal or arbitrary file access
- Remote code execution
- Cross-site scripting (XSS) in admin dashboard
- Audit log tampering

### Out of Scope

- Denial of service (DoS) attacks
- Social engineering
- Issues in WordPress core or third-party plugins
- Issues requiring physical access to the server
- Attacks that require an already-compromised Administrator account
