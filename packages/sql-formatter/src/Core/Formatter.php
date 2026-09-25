<?php

declare(strict_types=1);

namespace SqlFormatter\Core;

use SqlFormatter\Core\Layout\Renderer;
use SqlFormatter\Core\Syntax\Document;
use SqlFormatter\Core\Syntax\Fingerprint;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\SqlParser;

/**
 * Formats and verifies SQL using injected parsing and formatting contracts.
 *
 * @visibility SqlFormatter
 */
final class Formatter
{
    /**
     * Reuses a configured parser and an immutable layout preset across calls.
     */
    public function __construct(
        private readonly SqlParser $parser,
        private readonly Dialect $dialect,
        private readonly FormatOptions $options = new FormatOptions(),
    ) {
    }

    /**
     * @throws SourceException When the input is not accepted by the selected grammar
     * @throws FormattingException When output verification detects changed syntax
     */
    public function format(string $sql): string
    {
        $tree = $this->parser->parse($sql);
        if ($this->options->style === Style::Compact) {
            return (new Compact\Renderer($this->parser, $this->dialect->compactRules()))->render($tree);
        }
        $result = (new Renderer(Document::from($tree, $this->dialect->syntaxRules()), $this->options))->render();
        try {
            $formatted = $this->parser->parse($result);
        } catch (SourceException $exception) {
            throw new FormattingException('Formatted SQL could not be parsed with the original grammar.', 0, $exception);
        }
        if (Fingerprint::of($tree) !== Fingerprint::of($formatted)) {
            throw new FormattingException('Formatting changed the SQL syntax tree.');
        }
        return $result;
    }
}
