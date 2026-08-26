<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\IdentityGenerationStrategy;
use ZtdQuery\Schema\TablePartitioning;

/**
 * Rewrites a statement so that it reads the shadow instead of the database.
 *
 * A transformer holds nothing: everything it needs about a table is handed to
 * it, so the same transformer can rewrite one statement against one shadow and
 * the next against another.
 *
 * @phpstan-import-type Row from StatementInterface
 *
 * @phpstan-type ShadowView array{viewSql: string}
 * @phpstan-type ShadowRows array{
 *     rows: list<Row>,
 *     columns: array<int, string>,
 *     columnTypes: array<string, ColumnDeclaration>,
 *     primaryKeys?: array<int, string>,
 *     candidateKeys?: array<string, array<int, string>>,
 *     columnDefaults?: array<string, string>,
 *     identityStrategies?: array<string, IdentityGenerationStrategy>,
 *     generatedExpressions?: array<string, string>,
 *     partitioning?: TablePartitioning|null,
 *     sourceSql?: string,
 *     storageTable?: string
 * }
 * @phpstan-type ShadowTable ShadowView|ShadowRows
 * @phpstan-type ShadowTables array<string, ShadowTable>
 */
interface SqlTransformer
{
    /**
     * Rewrites a statement so that it reads these tables' shadow rows.
     *
     * A table given as a view is rewritten to the statement that defines it;
     * a table given as rows is rewritten to those rows written out.
     *
     * @param string $sql The statement, as it was written
     * @param ShadowTables $tables Table name => what the shadow holds for it
     *
     * @return string The statement, rewritten to read the shadow
     */
    public function transform(string $sql, array $tables): string;
}
