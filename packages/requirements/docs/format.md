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
requirement provenance. Both appear under `spec --no-test --without-source`; filter an exact
origin with `--origin`. A source-free file may instead hold sourced specifications
with requirement links; those remain sourced and are not independent items.

## Source formats and selectors

| Format | Scope/evidence selector | Unit |
| --- | --- | --- |
| `html` | CSS scopes; CSS or exact Text Fragment evidence | Selected element text |
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

Markdown is a document for readers. Each item starts with its ID, a compact row
of attribute badges, and the statement. Original source text appears as a quotation
with a link back to its source. Relationships and rationale follow the quotation.
The underlying information is the same as YAML, but the reading order and visual
presentation are designed for prose.

See the [rendered grammar example](../examples/markdown/grammar.md) and
[independent decision](../examples/markdown/decisions.md). A typical item looks like:

```markdown
# GENERATOR-001

![unsupported](https://img.shields.io/badge/status-unsupported-orange)

The generator shall produce C code.

> The generator produces C code.

[Source](../source.html#generation)

**unsupported reason**

The parser reads grammars and does not generate C code.
```

Here the badge carries the status, the block quotation carries the evidence, and
the link identifies both the source and its selected element. There are no `status`
or `evidence` field headings to read past. Reasons use **unsupported reason** or
**rationale**; **unsupport reason** is also accepted.

### Document setup

Opt in through the YAML configuration:

```yaml
version: 1
definitions: [requirements/*.md]
markdown:
  experimental: true
```

Each definition keeps document-level metadata in YAML frontmatter: `version`,
`source` and optional `$schema`. Items use H1 headings. For example:

```markdown
---
version: 1
source:
  id: grammar-manual
  uri: https://example.org/manual.html
  format: html
  selector: main p
$schema: https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.document.yaml
---

# REQ-001

![requirement](https://img.shields.io/badge/kind-requirement-blue)

A name starts with a letter.

> A name starts with a letter.

[Source](https://example.org/manual.html#names)

# SPEC-001

![lexical](https://img.shields.io/badge/category-lexical-blue)
![grammar](https://img.shields.io/badge/label-grammar-blue)

When a name is read, the parser shall require a leading letter.

**requirements**

- [REQ-001](#req-001)

**tests**

- **unit:** Tests\Unit\NameTest::testLeadingLetter
```

### Badges

Badges immediately follow the item heading. `kind`, `status`, `origin` and
`category` each have at most one value. Labels can have several values. Omitted
attributes keep the same defaults as YAML; ordinary supported specifications
need no kind/status badge.

The image alt text is the value. For [Shields static badges](https://shields.io/badges/static-badge),
the `kind-`, `status-`, `origin-`, `category-` or `label-` prefix identifies its role.
Lint requires the static image's role and message to agree with its alt text.
Color and style are presentation choices and survive formatting.

Custom/local images can specify the role using a Markdown image title:

```markdown
![lexical](assets/lexical.svg "category")
![original](assets/original.svg "origin")
![grammar](assets/grammar.svg)
```

An image with no recognized role or title is a label. Images are never downloaded
by lint, check or format; local images work without a badge service. Duplicate
attributes/labels and conflicting badge/field declarations fail lint.

### Quotations and source navigation

Place quotations immediately after the statement. Each quotation represents one
complete source unit and has one source citation. The citation can be immediately
below the quotation or the last paragraph inside it; the formatter places it below.
For several evidence units, write several quotation/citation pairs.

The source link must point to the resource declared in frontmatter. Local citation
paths are relative to the Markdown file, while `source.uri` stays relative to the
configuration. Formatting computes the correct relative link for nested documents.

For HTML/XML/Markdown sources, a simple element-ID fragment such as `#names`
supplies the CSS evidence selector. More complex selectors, JSONPath selectors,
line selections and extension-specific selectors use a hidden comment:

```markdown
> <!-- selector: main > p:first-child -->
> A name starts with a letter.

[Source](https://example.org/manual.html#:~:text=A%20name%20starts%20with%20a%20letter.)
```

The comment is machine-readable annotation and is invisible in a rendered document.
The spelling `<!-- **selector:** \#names -->` is also accepted. It must be the first
line inside the quotation. Arbitrary HTML/comments are rejected. HTML entity escapes
protect `<`, `>` and `&` inside selector comments; CSS backslash escapes are retained.

An explicit selector identifies the unit for verification; the source link helps
the reader navigate. `format` adds a missing citation, uses an element-ID anchor
when possible, and otherwise generates an exact Text Fragment for HTML or a
resource link for other formats. When the citation itself identifies the same
selector, the formatter omits the redundant selector comment.

HTML evidence can also use an exact [Text Fragment](https://wicg.github.io/scroll-to-text-fragment/#syntax)
as its selector, so a quotation and its source link are sufficient:

```markdown
> A name starts with a letter.

[Source](https://example.org/manual.html#:~:text=A%20name%20starts%20with%20a%20letter.)
```

This maps to the YAML evidence selector
`#:~:text=A%20name%20starts%20with%20a%20letter.`. The source scope remains `main p`:
coverage still enumerates that scope independently. After percent decoding and
whitespace normalization, the directive must match a **complete, unique scoped
unit**, case sensitively. Missing or duplicate matches fail `check` and do not
contribute coverage. Text Fragment evidence cannot replace the coverage scope.

This initial implementation supports one exact `text=` directive. Range selectors,
prefix/suffix context and multiple directives fail explicitly. Encode literal
commas, ampersands and dashes as `%2C`, `%26` and `%2D`. Browser scrolling/highlighting
uses browser-specific navigation behavior across the page; the tool's scoped
verification is stricter and does not require a browser. An optional element-ID
fallback in the URL does not disambiguate duplicate scoped text.

### Relationships, tests and supporting material

Keep the remaining material after the quotations, using named sections only when
there is something to say:

```markdown
**requirements**

- [REQ-001](reference.md#req-001)

**related**

- [SPEC-002](other.md#spec-002)

**design**

- [Name handling](https://example.org/design/names)
- Preserve the spelling in the syntax tree.
```

Reference labels are item IDs. Lint checks that each local file contains the loaded
ID, and that an optional heading fragment matches the lowercased ID with dots
removed. A YAML target can use `[REQ-003](other.yaml)`. External documents belong
in `design`. Test lists use the runner name in bold followed by a colon and target.

For source-free items, use `source: null`, an `original` or `undocumented` origin
badge, and a **rationale** paragraph. They appear in `spec --no-test --without-source`.

Optional project-specific metadata uses nested bullet lists:

```markdown
**metadata**

- **owner:** Parser team
- **reviewed:** true
- **priority:** 2
- **reviewers:**
  - Alice
  - Bob
```

JSON scalar spelling preserves types: `true`, `2`, `null`, `[]` and `{}` are typed
values; other text is a string. Quote ambiguous strings such as `"true"`, and use
`""` for an empty string. A nested mapping/list inside a sequence uses `- []` as its
parent entry. Empty list fields can be omitted or written as `None.` under their
field name; empty metadata is `{}`.

### Validation and formatting

The bundled [document-schema profile](../schemas/definition.document.yaml) follows
[document-schema.org draft 2026-06](https://document-schema.org/). CommonMark parsing
and profile validation run entirely in PHP. The evaluator implements the subset
needed by this profile: frontmatter JSON Schema, heading patterns/depth, section
counts, allowed block types and ordered block matching. Selector comments are
non-rendered annotations validated by the package's quotation reader. Badge roles,
citation targets and item structure are validated by the mapper. No external
executable is needed; custom document-schema profiles/mappings are unsupported.

Normalized documents pass the same JSON Schema, EARS and cross-file graph checks
as YAML. `$schema` may name the bundled document profile or a local JSON Schema
adding constraints to the normalized data. Item headings use ATX `# ID`. Nested or
setext headings, extra unlabelled paragraphs, unknown/duplicate fields, ordered
lists, code blocks and arbitrary HTML fail validation.

`format` preserves the file type, badge image URLs/titles, source citations and
cross-file reference destinations. It uses the reading order shown above and is
idempotent. Prose/emphasis normalize to text, literal statement backticks survive,
and YAML frontmatter comments are removed. The earlier field-oriented Markdown
syntax remains readable for migration; formatting rewrites it into badges and
quotations.

Markdown remains experimental while authoring effort, readability and review diffs
are evaluated. YAML remains supported. Markdown **source documents**, selected
through CSS over rendered HTML, are a separate source adapter.

See [lint](lint.md), [CLI](cli.md) and [extensions](extensions.md) for other rules
and commands.
