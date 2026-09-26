<?php

declare(strict_types=1);

namespace Tests\Fake;

/**
 * One grammar contract defined twice, once in YAML and once as Markdown cards, which must load
 * into the same model.
 *
 * Both projects quote the same source.html; the Markdown project reads every *.md file.
 */
final class GrammarCards
{
    /**
     * The quoted grammar contract with two rule paragraphs and one paragraph no item quotes.
     */
    public const SOURCE = <<<'HTML'
<!DOCTYPE html>
<html lang="en"><head><title>Example grammar contract</title></head><body><main>
<p id="names">A name starts with a letter.</p>
<p id="generation">The generator produces C code.</p>
<p id="unreviewed">A name may contain digits after its first character.</p>
</main></body></html>

HTML;

    /**
     * The configuration of the YAML project.
     */
    public const YAML_CONFIG = <<<'YAML'
version: 1
definitions:
  - grammar.yaml
  - decisions.yaml
coverage:
  minimum: 60
$schema: 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/config.schema.json'

YAML;

    /**
     * The sourced items of the YAML project.
     */
    public const YAML_GRAMMAR = <<<'YAML'
version: 1
source:
  id: example-grammar
  uri: source.html
  format: html
  selector: 'main p'
items:
  -
    id: REQ-001
    kind: requirement
    statement: 'A name starts with a letter.'
    evidence:
      -
        selector: '#names'
        quote: 'A name starts with a letter.'
  -
    id: SPEC-001
    statement: 'When a name is read, the parser shall require a leading letter.'
    requirements:
      - REQ-001
    labels:
      - grammar
    category: lexical
  -
    id: GENERATOR-001
    statement: 'The generator shall produce C code.'
    status: unsupported
    reason: 'The parser reads grammars and does not generate C code.'
    evidence:
      -
        selector: '#generation'
        quote: 'The generator produces C code.'
$schema: 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.schema.json'

YAML;

    /**
     * The original items of the YAML project.
     */
    public const YAML_DECISIONS = <<<'YAML'
version: 1
source: null
items:
  -
    id: READER-001
    statement: 'If input ends inside a rule, then the reader shall report an error.'
    origin: original
    reason: 'A partial tree would silently lose input.'
    labels:
      - strictness
    related:
      - SPEC-001
    design:
      -
        text: 'Retain source positions for error diagnostics.'
$schema: 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/definition.schema.json'

YAML;

    /**
     * The configuration of the Markdown project.
     */
    public const MARKDOWN_CONFIG = <<<'YAML'
$schema: 'https://raw.githubusercontent.com/k-kinzal/ztd-query-php/main/packages/requirements/schemas/config.schema.json'
version: 1
definitions:
  - '*.md'
markdown:
  experimental: true

YAML;

    /**
     * The sourced items of the Markdown project.
     */
    public const MARKDOWN_GRAMMAR = <<<'MD'
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

MD;

    /**
     * The original items of the Markdown project.
     */
    public const MARKDOWN_DECISIONS = <<<'MD'
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

MD;

    /**
     * Writes the YAML project into a directory.
     *
     * @param ProjectDirectory $project The project directory
     *
     * @return string The path of its requirements.yaml
     */
    public static function yaml(ProjectDirectory $project): string
    {
        $project->put('yaml/source.html', self::SOURCE);
        $project->put('yaml/grammar.yaml', self::YAML_GRAMMAR);
        $project->put('yaml/decisions.yaml', self::YAML_DECISIONS);
        return $project->put('yaml/requirements.yaml', self::YAML_CONFIG);
    }

    /**
     * Writes the Markdown project into a directory.
     *
     * @param ProjectDirectory $project The project directory
     *
     * @return string The path of its requirements.yaml
     */
    public static function markdown(ProjectDirectory $project): string
    {
        $project->put('markdown/source.html', self::SOURCE);
        $project->put('markdown/grammar.md', self::MARKDOWN_GRAMMAR);
        $project->put('markdown/decisions.md', self::MARKDOWN_DECISIONS);
        return $project->put('markdown/requirements.yaml', self::MARKDOWN_CONFIG);
    }
}
