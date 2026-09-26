# Definitions

A definition file traces one source document. It declares the source, the part of it you intend to cover, and the items that cover it: requirements quoted from the source, and specifications that restate them in [EARS](lint.md#ears) and link the tests that verify them.

## Example

```yaml
$schema: https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.schema.json
version: 1
source:
  id: grammar-manual
  uri: https://example.org/releases/1/manual.html
  format: html
  selector: 'main p'
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
        target: features/names.feature:12
  - id: SPEC-002
    statement: The generator shall produce C code.
    status: unsupported
    reason: This library reads grammar files and does not generate parsers.
    evidence:
      - selector: '#generation'
        quote: The generator produces C code.
```

`selector: 'main p'` makes every paragraph in `main` a unit to cover. `REQ-001` quotes one of them and `SPEC-001` implements it; `SPEC-002` quotes another and records why it is not implemented. Both count as covered.

## Source

| Key | Required | Description |
|-----|----------|-------------|
| `id` | yes | Unique source ID. Coverage thresholds refer to it. |
| `uri` | yes | URL or path of the document. Use one spelling per document. |
| `format` | yes | How the document is read. See [Formats](#formats). |
| `selector` | yes | The scope: the units of the document to cover. |
| `snapshot` | no | Path of a local cache of the document. Requires `sha256`. |
| `sha256` | no | SHA-256 of the document. A download that does not match is rejected. |
| `options` | no | Settings passed to a custom source extension. |

Write `source: null` for items that have no source; see [Items without a source](#items-without-a-source).

### Formats

| Format | Selector | Unit |
|--------|----------|------|
| `html` | CSS; evidence may also use a Text Fragment | Text of a selected element |
| `xml` | CSS, e.g. `section > rule` | Text of a selected element |
| `ietf` | CSS over RFC XML, e.g. `section[anchor="rules"] > t` | An RFC paragraph |
| `markdown` | CSS over the rendered HTML, e.g. `h2 + p`, `li` | Text of a selected element |
| `json` | JSONPath: names, quoted keys, indices, `*` and `..`, e.g. `$.rules[*].text` | A JSON value |
| `text` | `lines:10-30` or `lines:12` | A nonblank line |

JSONPath filters, slices and unions are not supported. A scope must not select an element together with one of its descendants. Other formats can be added with a [source extension](extensions.md).

### Snapshots

A snapshot keeps a verified copy of a remote document so that later runs do not download it again. It is a cache: add `.requirements-cache/` (or wherever you keep snapshots) to `.gitignore` instead of committing upstream documents. A missing snapshot is downloaded and checked against `sha256` automatically; `--live` reads the document from its URI instead. Downloads are limited to 16 MiB and 20 seconds.

## Items

| Key | Description |
|-----|-------------|
| `id` | Required. Unique across the project. Starts with a letter, then letters, digits, `.`, `_` or `-`. |
| `statement` | Required. A specification must use an EARS pattern; a requirement may be any prose. |
| `kind` | `specification` (default) or `requirement`. |
| `evidence` | Quotations of the source, each `{selector, quote}`. |
| `requirements` | IDs of the requirements a specification implements. |
| `tests` | Tests that verify a specification, each `{runner, target}`. |
| `status` | `supported` (default) or `unsupported`. |
| `reason` | Why the item is unsupported or has no source. |
| `origin` | `sourced` (default), `original` or `undocumented`. See [Items without a source](#items-without-a-source). |
| `labels`, `category` | Values to filter `spec` by. |
| `related` | IDs of related items of either kind. |
| `design` | Design notes, each with a `url`, a `text`, or both. |
| `metadata` | Any mapping, kept as is. |

### Evidence

Each evidence entry selects exactly one unit inside the source scope and quotes its complete text; only whitespace may differ. `check` fails when the quote no longer matches, the selector finds nothing or several units, or the unit is outside the scope. One unit may be quoted by several items.

### Requirements and specifications

Requirements are optional. Use them when one passage of the source becomes several specifications: the requirement quotes it, and each specification lists it in `requirements` and is covered through it. A requirement cannot have tests, parent requirements or the `unsupported` status, and a requirement alone does not cover a unit.

### Tests

`target` selects one test for the named runner: `Class::method` for PHPUnit, with all its data sets, and `file.feature:line` for Behat, with all its outline examples. A supported specification without tests fails `spec`. An unsupported specification needs a `reason` and its tests are not run.

### Items without a source

Behavior that no source describes is declared in a file with `source: null`:

```yaml
version: 1
source: null
items:
  - id: READER-001
    statement: If input ends inside a rule, then the reader shall report an error.
    origin: original
    reason: Reject incomplete input instead of silently losing the final rule.
    tests:
      - runner: behavior
        target: features/reader-decisions.feature:14
```

Use `original` for a deliberate decision of the project and `undocumented` for behavior whose source has not been found yet. Such items cannot have evidence or requirements, and `spec --without-source` lists them. A file with `source: null` may instead hold specifications that link requirements of other files; those count as sourced.

## Markdown definitions

Definitions can also be written as Markdown documents that read well on GitHub. The format is experimental and must be enabled with `markdown.experimental: true` in the [configuration](configuration.md). Both formats produce the same items.

```markdown
---
version: 1
source:
  id: grammar-manual
  uri: https://example.org/manual.html
  format: html
  selector: main p
---

# REQ-001

![requirement](https://img.shields.io/badge/kind-requirement-blue)

A name starts with a letter.

> A name starts with a letter.

[Source](https://example.org/manual.html#names)

# SPEC-001

![grammar](https://img.shields.io/badge/label-grammar-blue)

When a name is read, the parser shall require a leading letter.

**requirements**

- [REQ-001](#req-001)

**tests**

- **unit:** Tests\Unit\NameTest::testLeadingLetter
```

| Part | Written as |
|------|------------|
| File settings | YAML frontmatter with `version`, `source` and an optional `$schema` |
| Item | `# ID` heading, then badges, then the statement paragraph |
| `kind`, `status`, `origin`, `category`, `labels` | Badge images right after the heading. The alt text is the value; the role is the `kind-`, `status-`, `origin-`, `category-` or `label-` prefix of a [Shields static badge](https://shields.io/badges/static-badge), or the image title. An image without a role is a label. |
| Evidence | A block quotation followed by a `[Source](...)` link to the declared source |
| `reason` | A **unsupported reason** or **rationale** paragraph |
| `requirements`, `related` | A bold field name followed by a list of links such as `[REQ-001](other.md#req-001)` |
| `tests` | A **tests** list of `**runner:** target` entries |
| `design`, `metadata` | A **design** list of links or text, and a **metadata** list of `**key:** value` entries |

The evidence selector comes from the link: `#names` selects the element with that ID, and for HTML a Text Fragment such as `#:~:text=A%20name%20starts%20with%20a%20letter.` selects the one unit whose complete text it spells. Any other selector is written as the first line of the quotation, as `<!-- selector: main > p:first-child -->`.

`requirements format` rewrites a document into this layout and adds missing source links. Headings other than `# ID`, extra paragraphs, code blocks and HTML other than selector comments fail lint.

## Schema

Add the definition schema to each YAML definition, as in the example above. Markdown definitions can name `https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.document.yaml` in their frontmatter. Both resolve offline, like the [configuration schema](configuration.md#schema).
