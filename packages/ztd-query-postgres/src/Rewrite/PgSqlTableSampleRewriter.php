<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Table sample rewriter for PostgreSQL queries.
 */
final class PgSqlTableSampleRewriter
{
    private PgSqlTableSampleParser $parser;
    private PgSqlIdentifierQuoter $quoter;

    /**
     * Initializes the collaborators and state used by this table sample rewriter.
     */
    public function __construct()
    {
        $this->parser = new PgSqlTableSampleParser();
        $this->quoter = new PgSqlIdentifierQuoter();
    }

    /**
     * @param array<string, array<string, mixed>> $tables
     * @throws UnsupportedSqlException
     */
    public function rewrite(string $sql, array $tables): string
    {
        $samples = $this->parser->parse($sql);
        usort($samples, static fn (PgSqlTableSample $left, PgSqlTableSample $right): int => $right->startOffset <=> $left->startOffset);

        foreach ($samples as $index => $sample) {
            $columns = (new Rewrite\Sampling\TableColumns())->columns($sample->tableName, $tables);
            if ($columns === []) {
                throw new UnsupportedSqlException(
                    $sql,
                    "Cannot determine columns for TABLESAMPLE source '{$sample->tableName}'",
                );
            }
            $replacement = (new Rewrite\Sampling\SampleProjection($this->quoter))->replacement($sample, $columns, $index);
            $sql = substr_replace(
                $sql,
                $replacement,
                $sample->startOffset,
                $sample->endOffset - $sample->startOffset,
            );
        }

        return $sql;
    }
}
