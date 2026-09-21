---
title: "FAQ"
nav_order: 9
description: "Short answers about signing PDF documents in Laravel: what the package is, versions, link expiry, positions, storage, the portal, the audit trail and testing."
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
