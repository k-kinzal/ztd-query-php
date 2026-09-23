---
version: 1
source: null
$schema: 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.document.yaml'
---

# READER-001

![original](https://img.shields.io/badge/origin-original-blue)
![strictness](https://img.shields.io/badge/label-strictness-blue)

If input ends inside a rule, then the reader shall report an error.

**rationale**

A partial tree would silently lose input.

**related**

- [SPEC-001](grammar.md#spec-001)

**design**

- Retain source positions for error diagnostics.
