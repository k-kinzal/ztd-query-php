# Document format (version 1)

Configuration uses YAML. Definitions use YAML or the opt-in experimental
Markdown profile below; both produce the same version 1 data model. Paths are relative to the configuration
file's directory; definition file locations do not change that base. Markdown
reference links are relative to the Markdown file so they work in document viewers. Definition
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

Opt in to the Markdown profile:

```yaml
version: 1
definitions: [requirements/*.md]
markdown:
  experimental: true
```

Parsing and validation run entirely in PHP using Composer dependencies. The bundled
[document-schema profile](../schemas/definition.document.yaml) follows
[document-schema.org draft 2026-06](https://document-schema.org/). The internal
evaluator implements the subset needed by this profile: frontmatter JSON Schema,
heading patterns and depth, section counts, allowed block types, and ordered block
matching with repetition. It is not a general-purpose document-schema validator;
custom document profiles and mappings are not supported. Unsupported profile
keywords fail explicitly. No external executable is needed.

Configuration stays YAML. A Markdown definition uses YAML frontmatter only for
`version`, `source` and optional `$schema`. The body uses one H1 per item, a
statement paragraph, then bold field names and native Markdown values:

```markdown
---
$schema: https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.document.yaml
version: 1
source:
  id: grammar-manual
  uri: https://example.org/manual.html
  format: html
  selector: main p
---

# REQ-001

A name starts with a letter.

**kind**

requirement

**evidence**

- **selector:** #names

  > A name starts with a letter.

# SPEC-001

When a name is read, the parser shall require a leading letter.

**requirements**

- [REQ-001](#req-001)

**tests**

- **unit:** Tests\Unit\NameTest::testLeadingLetter

**labels**

![grammar](https://img.shields.io/badge/label-grammar-blue)

**category**

lexical

**design**

- [Name handling](https://example.org/design/names)
- Preserve the spelling in the syntax tree.
```

No item fields are encoded in code blocks. This example is shown as literal
Markdown for copying; see the [rendered example](../examples/markdown/grammar.md)
for its document presentation. Evidence uses a selector list entry followed by an
indented block quotation. Several selector/quotation entries represent several
pieces of evidence.

| Field | Markdown representation |
| --- | --- |
| `id` | H1 heading (`# SPEC-001`) |
| `statement` | First paragraph after the heading |
| `kind`, `status`, `origin`, `category`, `reason` | Bold field name, blank line, then prose |
| `evidence` | Bullet list of bold `selector:` values, each followed by a nested block quotation |
| `requirements`, `related` | Bullet list of links whose visible labels are item IDs |
| `tests` | Bullet list with the runner name in bold followed by a colon and target |
| `labels` | Badge images whose alt text is the label, or a bullet list of plain labels |
| `design` | Bullet list of standalone links or prose paragraphs |
| `metadata` | Nested bullet lists with bold keys for mappings and plain values for sequences |

Reference links can target the same file (`[REQ-001](#req-001)`), another Markdown
file (`[REQ-002](other.md#req-002)`) or a YAML definition (`[REQ-003](other.yaml)`).
The visible ID is the graph edge; lint also checks that the local file contains
that loaded ID. Optional fragments must use the lowercased ID with dots removed,
matching the conventional Markdown heading anchor. Remote requirement links are
not supported; use `design` for external documentation links.

A badge's URL controls presentation only. Its alt text supplies the label used by
filters. Local images and remote badge services both work; validation never
fetches them. Plain lists are equally valid. Formatting preserves existing badge
URLs and reference destinations, including cross-file paths.

Metadata preserves scalar types using JSON spelling: `true`, `2`, `null`, `[]`
and `{}` represent their corresponding values; other plain text is a string.
Quote ambiguous strings, such as `"true"`, and write `""` for an empty string.
Nested mappings and lists use native indentation:

```markdown
**metadata**

- **owner:** Parser team
- **reviewed:** true
- **priority:** 2
- **literal:** "true"
- **reviewers:**
  - Alice
  - Bob
- **release:**
  - **version:** "1"
```

A sequence entry containing a nested mapping or list uses `- []` followed by the
nested list. Empty list fields can be omitted or written as `None.` after their
bold field name; empty metadata is `{}`.

The reader maps these blocks to the same data model as YAML. Normalized documents
pass the same JSON Schema, EARS validator and cross-file graph checks. `$schema`
may be omitted or name the bundled document profile; a local JSON Schema instead
adds constraints on the normalized model. Item headings must use ATX `# ID`;
setext or nested headings, extra unlabelled paragraphs, duplicate/unknown fields,
ordered lists, raw HTML and code blocks fail validation. Prose supports emphasis
and inline code spans; use standalone links in reference/design fields rather
than embedding links inside prose.

Statements fold physical line breaks to spaces. Formatting normalizes prose and
emphasis to text, preserves literal backticks in statements, escapes Markdown
punctuation where needed, and removes YAML comments in frontmatter. It preserves
the document type and is idempotent. See paired
[YAML](../examples/decisions.yaml) and
[Markdown](../examples/markdown/decisions.md) examples. Use the configuration in
the Markdown directory to try it without loading duplicate IDs.

Markdown support is experimental. YAML stays supported while authoring effort,
readability and review diffs are evaluated; this does not decide whether one or
both formats remain long term. Markdown **source documents**, selected through
CSS over rendered HTML, are a separate source adapter.

See [lint](lint.md) for EARS syntax rules, [CLI](cli.md) for commands and
[extensions](extensions.md) for custom sources and test runners.
