<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Layout;

use SqlFormatter\Core\FormatOptions;
use SqlFormatter\Core\Syntax\Document;

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
        (new Block($this->document, $this->writer, $this->options))->render(0, count($this->document->tokens) - 1, 0);
        return $this->writer->finish($this->document->trailing);
    }

}
