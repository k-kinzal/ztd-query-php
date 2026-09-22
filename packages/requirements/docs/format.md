# Document format (version 1)

Configuration uses YAML. Definitions use YAML or the opt-in experimental
Markdown profile below; both produce the same version 1 data model. Paths are relative to the configuration
file's directory; definition file locations do not change that base. Definition
patterns use PHP `glob` syntax (`*` is not a recursive glob). Overlapping patterns
load a file once. Every pattern must match. Unknown fields fail lint, except keys
inside extension `options` and item `metadata`.

```yaml
version: 1
definitions:
  - requirements/*.yaml
bootstrap: vendor/autoload.php
extensions:
  sources:
    company: App\Traceability\CompanySource
  runners:
    custom: App\Traceability\CustomRunner
runners:
  unit:
    extension: phpunit
    command: [php, vendor/bin/phpunit, --configuration, phpunit.xml.dist]
    cwd: .
    timeout: 60
  behavior:
    extension: behat
    command: [php, vendor/bin/behat]
    timeout: 60
coverage:
  minimum: 80
  diff_minimum: 0
  sources:
    grammar-manual: 80
```

`bootstrap`, `extensions`, `runners` and `coverage` are optional. Commands must be
nonempty argument lists. Thresholds range from 0 to 100. Source threshold keys must
name an existing source. Extensions are instantiated without constructor arguments.
The bootstrap is project code and must be trusted, like the test suite itself.

Each definition declares one source, or explicitly `source: null`:

```yaml
version: 1
source:
  id: grammar-manual
  uri: https://example.org/releases/1/manual.html
  format: html
  selector: 'main p'
  snapshot: .requirements-cache/manual.html
  sha256: <64 lowercase hexadecimal characters>
  options: {}
items:
  - id: REQ-001
    kind: requirement
    statement: A name starts with a letter.
    evidence:
      - selector: '#names'
        quote: A name starts with a letter.
  - id: SPEC-001
    statement: When a name is read, the parser shall require a leading letter.
    requirements: [REQ-001]
    tests:
      - runner: unit
        target: 'Tests\Unit\NameTest::testLeadingLetter'
      - runner: behavior
        target: spec/features/names.feature:12
    labels: [grammar, lexical]
    category: names
    related: [SPEC-002]
    design:
      - url: https://example.org/design/names
      - text: Preserve the spelling in the concrete syntax tree.
    metadata:
      owner: parser-maintainers
  - id: SPEC-002
    statement: The generator shall produce C code.
    status: unsupported
    reason: This library reads grammar files and does not generate parsers.
    evidence:
      - selector: '#generation'
        quote: The generator produces C code.
```

Source IDs and item IDs must be unique throughout the project. Item IDs start with
a letter and otherwise contain letters, digits, dots, underscores or dashes.
`kind` defaults to `specification`, `status` to `supported`, and `origin` to
`sourced`. Tests, labels, related links, requirement links and evidence are lists.
Each evidence entry has exactly `selector` and `quote`. Each test has `runner` and
`target`. Items may contain several evidence entries and several tests. A full
quoted unit can be linked to multiple specifications.

Requirements are optional. They cannot have tests, requirement parents or an
unsupported status. A specification may derive from multiple sourced requirements
across definition files, have its own evidence, or both. A requirement cannot be
its own reference. General `related` edges may connect either kind and can be
reciprocal. Dangling, duplicate or self links fail lint. Design records accept
`url`, `text`, or both. Metadata is an unrestricted mapping.

Source-free declarations require an explicit origin and reason:

```yaml
version: 1
source: null
items:
  - id: READER-001
    statement: If input ends inside a rule, then the reader shall report an error.
    origin: original
    reason: Reject incomplete input instead of silently losing the final rule.
    labels: [strictness]
    tests:
      - runner: behavior
        target: spec/features/reader-decisions.feature:14
```

Use `original` for deliberate project behavior and `undocumented` for behavior
whose source has not yet been identified. Neither may claim source evidence or
requirement provenance. Both appear under `list --without-source`; filter an exact
origin with `--origin`. A source-free file may instead hold sourced specifications
with requirement links; those remain sourced and are not independent items.

## Source formats and selectors

| Format | Scope/evidence selector | Unit |
| --- | --- | --- |
| `html` | CSS, e.g. `main p`, `#names` | Selected element text |
| `xml` | CSS, e.g. `section > rule` | Selected element text |
| `ietf` | RFC XML CSS, e.g. `section[anchor="rules"] > t` | Selected RFC paragraph; use `html` or `text` for other RFC representations |
| `markdown` | CSS over CommonMark-rendered HTML, e.g. `h2 + p`, `li` | Selected element text; raw HTML is stripped |
| `json` | JSONPath child names, quoted keys, nonnegative indices, `*`, recursive names | Selected JSON value at a canonical JSON Pointer |
| `text` | `lines:10-30` or `lines:12` | Nonblank physical line |

JSONPath examples: `$.rules[*].text`, `$['rules'][0]['text']`, `$..requirement`.
Filters, slices, unions, escaped quoted property names and other JSONPath features
are intentionally unsupported and fail explicitly. Register a source extension
if a broader query dialect is needed. Objects/arrays are quoted as compact JSON;
strings are quoted as their text. Null, Boolean and numeric values use JSON text.

Scope selectors can select many units. Evidence selectors must select exactly
one of those units. Parent and descendant DOM elements cannot both be selected in
a single scope. URI spelling is significant: use one canonical URI for a resource.
A snapshot is an optional **untracked cache** and requires SHA-256. Its content
must be exactly the downloaded resource. Add `.requirements-cache/` to `.gitignore`;
missing snapshots are downloaded and verified automatically. Never commit upstream
HTML or other source documents just to make the check reproducible. A SHA-256 without a snapshot pins the URI contents too.
All built-in sources accept local paths or HTTP(S). HTTP reads are limited to
16 MiB and a 20-second total request duration (Symfony HttpClient; cURL recommended); extensions own their service-specific limits.

## Schema declarations and editors

Add `$schema` to configuration:

```yaml
$schema: https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/config.schema.json
version: 1
definitions: [requirements/*.yaml]
```

Use `.../schemas/definition.schema.json` in each YAML definition. These exact
canonical URIs resolve to installed files during lint, including offline. A relative
path such as `./team.schema.json` applies an additional local JSON Schema; it never
weakens the core schema. The same schemas can be configured in a YAML editor. For
editors using YAML Language Server, add a `# yaml-language-server: $schema=...`
comment or an editor schema association; the in-document `$schema` is consumed by
requirements itself. The formatter removes YAML comments, so editor associations
are useful when running it regularly.

## Experimental Markdown definitions

This profile uses [document-schema.org draft 2026-06](https://document-schema.org/)
and its [schematter reference validator](https://github.com/iwe-org/schematter).
Install **schematter 0.2.0** on PATH (for example
`cargo install schematter --version 0.2.0 --locked`), then opt in:

```yaml
version: 1
definitions: [requirements/*.md]
markdown:
  experimental: true
  command: [schematter]
  timeout: 30
```

Configuration remains YAML. `command` is an argument array, defaults to
`[schematter]`, and receives `validate FILE --schema PROFILE --format json`.
The timeout defaults to 30 seconds. Missing tooling, invalid structure, errors and
timeouts fail validation; the tool never silently treats Markdown as YAML.

A definition uses YAML frontmatter for the source and version, H1 headings for
item IDs, one paragraph for each statement and an optional YAML fence for all
remaining item fields:

````markdown
---
$schema: https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.document.yaml
version: 1
source: null
---

# READER-001

If input ends inside a rule, then the reader shall report an error.

```yaml
origin: original
reason: Avoid silently losing the final rule.
labels: [strictness]
tests:
  - runner: behavior
    target: spec/features/reader-decisions.feature:14
```
````

The bundled [document-schema profile](../schemas/definition.document.yaml) checks
headings, paragraph/fence order and multiplicity. The CommonMark reader then maps
each `# ID` to `items[].id`, the paragraph to `items[].statement`, and the YAML
fence to the rest of that item. The normalized document also passes the exact same
JSON Schema, EARS validator and cross-file checks as YAML. A heading must use
`# ID` (no setext headings). Nested headings, extra paragraphs, unknown frontmatter
fields, duplicate `id`/`statement` fields in the fence and non-YAML fences fail.

Paragraph text preserves inline Markdown spelling (including backticks) and folds
physical lines to spaces; it does not strip markup into a different statement.
Use simple prose and literal code spans in EARS statements. Item lists, evidence,
test references, labels, design and metadata have the same structure as YAML.
`$schema` may be omitted or name the bundled document profile; additional local
JSON Schemas constrain the normalized data. No user-defined Markdown mappings are
supported in this initial experiment.

`requirements format` preserves the input document type, emits canonical headings,
paragraphs and YAML fences, and is idempotent. It removes YAML comments and normalizes
line wrapping. See paired [YAML](../examples/decisions.yaml) and
[Markdown](../examples/markdown/decisions.md) examples. Use the example configuration
in that directory to try Markdown without loading duplicate IDs.

Markdown support is experimental. YAML stays supported while authoring effort,
readability and review diffs are evaluated; this does not decide whether one or both
formats will remain long term. Markdown **source documents**, selected through CSS
over rendered HTML, are a separate existing source adapter.

See [lint](lint.md) for the EARS syntax rules and [CLI](cli.md) for format/check flags.
