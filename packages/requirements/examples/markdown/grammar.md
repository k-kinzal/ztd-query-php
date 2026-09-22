---
version: 1
source:
  id: example-grammar
  uri: ../source.html
  format: html
  selector: 'main p'
$schema: 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.document.yaml'
---

# REQ-001

A name starts with a letter.

```yaml
kind: requirement
evidence:
  -
    selector: '#names'
    quote: 'A name starts with a letter.'
```

# SPEC-001

When a name is read, the parser shall require a leading letter.

```yaml
requirements:
  - REQ-001
labels:
  - grammar
category: lexical
```

# GENERATOR-001

The generator shall produce C code.

```yaml
status: unsupported
reason: 'The parser reads grammars and does not generate C code.'
evidence:
  -
    selector: '#generation'
    quote: 'The generator produces C code.'
```
