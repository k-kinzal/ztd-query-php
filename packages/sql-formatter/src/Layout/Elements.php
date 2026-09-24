<?php

declare(strict_types=1);

namespace SqlFormatter\Layout;

use SqlFormatter\Style;
use SqlFormatter\Syntax\Document;

/**
 * Emits expressions and delegates nested query and CASE blocks to the renderer.
 *
 * @visibility SqlFormatter
 */
final class Elements
{
    /**
     * Shares the output buffer while retaining the enclosing block's layout policy.
     */
    public function __construct(private readonly Document $document, private readonly Writer $writer, private readonly Block $renderer, private readonly Policy $policy)
    {
    }

    /**
     * Advances past nested blocks and returns any whitespace required before the next token.
     */
    public function write(int &$index, int $end, int $indent, string $pending): string
    {
        $token = $this->document->tokens[$index];
        $separator = $pending !== '' ? $pending : Spacing::between($this->document->tokens[$index - 1] ?? null, $token, isset($this->document->unary[$index - 1]));
        if (isset($this->document->logical[$index])) {
            $separator = $this->policy->options->style === Style::Expanded ? $this->policy->line($indent) : $this->policy->beforeHeader(strlen($token->text));
        }
        if (isset($this->document->caseBranches[$index]) || ($index > 0 && $this->document->tokens[$index - 1]->text === ';')) {
            $separator = $this->policy->line($this->policy->base);
        }
        $this->writer->token($token, $separator);
        $caseEnd = $this->document->casePairs[$index] ?? null;
        $close = $caseEnd ?? $this->document->pairs[$index] ?? null;
        if ($close !== null && $close <= $end && ($caseEnd !== null || isset($this->document->blocks[$index]))) {
            $blockBase = $this->policy->options->style === Style::Expanded ? $indent : $this->policy->base;
            $this->renderer->render($index + 1, $close - 1, $blockBase + $this->policy->options->indentWidth);
            $this->writer->token($this->document->tokens[$close], $this->policy->line($blockBase));
            $index = $close;
        } elseif (isset($this->document->commas[$index])) {
            return $this->policy->line($indent);
        } elseif (isset($this->document->logical[$index])) {
            return $this->policy->options->style === Style::Tabular ? $this->policy->afterHeader(strlen($token->text)) : ' ';
        }
        return '';
    }
}
