# Security Policy

## Supported Versions

Before the first tagged release, security fixes are handled against the current
`main` branch. After a release, support is provided for the latest release line
and the current default branch.

## Runtime And Deployment

Use PHP 8.4.27+ or 8.5.12+ and keep the runtime and operating system patched.
These minimums include the PHP security releases published on 2026-10-21.
CI runs `composer audit` to check installed dependencies for known advisories.

The native TCP listener handles one connection at a time. A slow client can
occupy the process until its read or write timeout expires. Do not expose it
directly to the public Internet; use a reverse proxy with TLS termination,
request buffering, connection limits, and rate limits.

Development images include Xdebug, passwordless sudo, and shell installers
downloaded during the build. Use only the `production` image target for
deployment, never a development image or the devcontainer.

Providers are process-global, not request-scoped. Do not cache credentials,
authenticated users, or other request-specific state in shared providers.

## Reporting A Vulnerability

Please do not open a public issue for a security vulnerability.

Report it privately to [vihoga@hotmail.es](mailto:vihoga@hotmail.es) with:

- A clear description of the problem
- Steps to reproduce it
- The affected BraidPHP version or commit
- Impact assessment if known
- Any suggested remediation or patch

Do not include credentials, tokens, or other secrets in the report. The maintainer
will acknowledge receipt as soon as possible and work toward a fix before public
disclosure.
