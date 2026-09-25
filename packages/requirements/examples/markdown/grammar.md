---
version: 1
source:
  id: example-grammar
  uri: source.html
  format: html
  selector: 'main p'
$schema: 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.document.yaml'
---

# REQ-001

![requirement](https://img.shields.io/badge/kind-requirement-blue)

A name starts with a letter.

> A name starts with a letter.

[Source](source.html#names)

# SPEC-001

![lexical](https://img.shields.io/badge/category-lexical-blue)
![grammar](https://img.shields.io/badge/label-grammar-blue)

When a name is read, the parser shall require a leading letter.

**requirements**

- [REQ-001](#req-001)

# GENERATOR-001

![unsupported](https://img.shields.io/badge/status-unsupported-orange)

The generator shall produce C code.

> The generator produces C code.

[Source](source.html#generation)

**unsupported reason**

The parser reads grammars and does not generate C code.
