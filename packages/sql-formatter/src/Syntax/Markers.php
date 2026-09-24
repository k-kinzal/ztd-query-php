<?php

declare(strict_types=1);

namespace SqlFormatter\Syntax;

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
    public function __construct(private readonly Document $document)
    {
    }

    /**
     * @param array<int, string> $direct
     */
    public function apply(string $name, int $start, int $end, array $direct): void
    {
        if (in_array($name, Rules::CLAUSES, true)) {
            (new Headers($this->document))->mark($start);
        }
        if (in_array($name, ['when_clause', 'when_list', 'case_exprlist', 'case_default', 'case_else', 'opt_else'], true)) {
            foreach ($direct as $index => $text) {
                if (in_array(strtoupper($text), ['WHEN', 'ELSE'], true)) {
                    $this->document->caseBranches[$index] = true;
                }
            }
        }
        foreach ($direct as $index => $text) {
            if (in_array($name, Rules::STATEMENTS, true) || Rules::isJoin($name)) {
                (new Headers($this->document))->mark($index);
            }
            if ($text === ',' && in_array($name, Rules::LISTS, true)) {
                $this->document->commas[$index] = true;
            }
        }
        if (in_array($name, ['groupby_opt', 'opt_partition_clause', 'window'], true)) {
            Lists::mark($this->document, $start, $end);
        }
        (new Expressions($this->document))->mark($name, $start, $end, $direct);
        if (in_array($name, ['create_table_tail', 'create_table_stmt', 'CreateStmt', 'create_table_args'], true)) {
            foreach ($direct as $index => $text) {
                if ($text === '(') {
                    $this->document->blocks[$index] = true;
                }
            }
        }
    }


}
