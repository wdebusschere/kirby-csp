# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

Please do not open a public issue for security problems.

Report vulnerabilities privately through GitHub:
[Report a vulnerability](https://github.com/wdebusschere/kirby-csp/security/advisories/new)
(Security tab → "Report a vulnerability"). If you cannot use GitHub, email
info@e-xperience.pt instead.

Please include:

- the affected version and your Kirby and PHP versions,
- the configuration involved,
- steps to reproduce, and the impact you expect.

## What to Expect

- An acknowledgement within 3 working days.
- An assessment and, for confirmed issues, a fix plan within 14 days.
- Confirmed issues are fixed in a patch release and published as a GitHub
  security advisory. Reporters are credited unless they ask not to be.

## Scope

In scope: the code in this repository — the policy builder, nonce
generation and the headers it sends. Issues in Kirby itself should go to
[Kirby's security policy](https://github.com/getkirby/kirby/security/policy).
