# Security policy

This package handles signed contracts and the identities of the people who sign them, so security reports are taken seriously.

## Supported versions

Only the latest minor release of 1.x receives security fixes. Upgrade before reporting.

## What counts as a vulnerability

For example:

- a signing page that opens without a valid signed URL, after the link expired, or for another signer's document;
- a way to sign a document twice, or to sign as someone else;
- an original, signature image or signed PDF that can be read or downloaded without being logged in to the portal;
- the audit trail missing an event or recording a wrong actor, IP address or user agent;
- a portal route that is reachable without the configured guard.

## Reporting a vulnerability

Please do **not** open a public issue. Report it privately instead:

- via [GitHub private vulnerability reporting](https://github.com/ArvidDeJong/laravel-document-sign/security/advisories/new), or
- by email to info@arvid.nl.

Include the package version, the Laravel version and the steps or request that reproduce it.

You will get a reply within a week. Once a fix is released, the advisory is published and you are credited, unless you prefer not to be.
