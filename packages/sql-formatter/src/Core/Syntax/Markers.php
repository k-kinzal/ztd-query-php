<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Syntax;

/**
 * Attaches layout roles at the grammar node that owns each construct.
 *
 * @visibility SqlFormatter
 */
final class Markers
{
    /**
     * Shares the document being annotated.
     */
    public function __construct(private readonly Document $document, private readonly Rules $rules)
    {
    }

    /**
     * @param array<int, string> $direct
     */
    public function apply(string $name, int $start, int $end, array $direct): void
    {
        if ($this->rules->has('clauses', $name)) {
            (new Headers($this->document, $this->rules))->mark($start);
        }
        if ($this->rules->has('branches', $name)) {
            foreach ($direct as $index => $text) {
                if (in_array(strtoupper($text), ['WHEN', 'ELSE'], true)) {
                    $this->document->caseBranches[$index] = true;
                }
            }
        }
        foreach ($direct as $index => $text) {
            if ($this->rules->has('statements', $name) || $this->rules->has('joins', $name)) {
                (new Headers($this->document, $this->rules))->mark($index);
            }
            if ($text === ',' && $this->rules->has('lists', $name)) {
                $this->document->commas[$index] = true;
            }
        }
        if ($this->rules->has('genericLists', $name)) {
            Lists::mark($this->document, $start, $end);
        }
        (new Expressions($this->document, $this->rules))->mark($name, $start, $end, $direct);
        if ($this->rules->has('tables', $name)) {
            foreach ($direct as $index => $text) {
                if ($text === '(') {
                    $this->document->blocks[$index] = true;
                }
            }
        }
    }


}
