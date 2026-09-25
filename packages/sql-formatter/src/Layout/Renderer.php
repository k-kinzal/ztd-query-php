<?php

declare(strict_types=1);

namespace SqlFormatter\Layout;

use SqlFormatter\FormatOptions;
use SqlFormatter\Style;
use SqlFormatter\Syntax\Document;

/**
 * Applies one layout to grammar-derived clauses, lists, and nested blocks.
 *
 * @visibility SqlFormatter
 */
final class Renderer
{
    private readonly Writer $writer;

    /**
     * Starts a fresh output buffer for the supplied document.
     */
    public function __construct(private readonly Document $document, private readonly FormatOptions $options)
    {
        $this->writer = new Writer();
    }

    /**
     * Writes a complete document and its final comments.
     */
    public function render(): string
    {
        if ($this->options->style === Style::Compact) {
            foreach ($this->document->tokens as $index => $token) {
                $this->writer->token($token, Spacing::between($this->document->tokens[$index - 1] ?? null, $token, isset($this->document->unary[$index - 1])));
            }
        } else {
            (new Block($this->document, $this->writer, $this->options))->render(0, count($this->document->tokens) - 1, 0);
        }
        return $this->writer->finish($this->document->trailing);
    }

}
