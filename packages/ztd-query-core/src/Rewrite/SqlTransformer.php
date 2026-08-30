<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Platform\ValueRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\Key\IdentityGenerationStrategy;
use ZtdQuery\Schema\Key\PartialUniqueIndex;
use ZtdQuery\Schema\Partition\TablePartitioning;
use ZtdQuery\Schema\TableDefinition;

/**
 * Rewrites a statement so that it reads the shadow instead of the database.
 *
 * A transformer holds nothing: everything it needs about a table is handed to
 * it, so the same transformer can rewrite one statement against one shadow and
 * the next against another.
 *
 * A shadow row's values are whatever the driver handed back, which is wider
 * than what a row holds once it has been read: a driver may answer a large
 * column as an open stream, or an object that says how it spells itself.
 *
 * @phpstan-import-type Row from TableDefinition
 * @phpstan-import-type RenderableValue from ValueRenderer
 *
 * @phpstan-type ShadowView array{viewSql: string}
 * @phpstan-type ShadowRows array{
 *     rows: list<array<string, RenderableValue>>,
 *     columns: array<int, string>,
 *     columnTypes: array<string, ColumnDeclaration>,
 *     primaryKeys?: array<int, string>,
 *     candidateKeys?: array<string, array<int, string>>,
 *     partialUniqueIndexes?: array<string, PartialUniqueIndex>,
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
