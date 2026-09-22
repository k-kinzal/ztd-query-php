---
version: 1
source: null
$schema: 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.document.yaml'
---

# READER-001

If input ends inside a rule, then the reader shall report an error.

**origin**

original

**reason**

A partial tree would silently lose input.

**labels**

![strictness](<https://img.shields.io/badge/label-strictness-blue>)

**related**

- [SPEC-001](<grammar.md#spec-001>)

**design**

- Retain source positions for error diagnostics.
