---
title: FAQ
nav_order: 6
description: Short answers about signing PDF documents in Laravel with darvis/laravel-document-sign, from signing links and positions to the portal and the audit trail.
faq: true
---

# Frequently asked questions

{% for item in site.data.faq %}
## {{ item.q }}

{{ item.a | markdownify }}
{% endfor %}
