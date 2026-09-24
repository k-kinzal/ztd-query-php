<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * CommitAction alternatives.
 *
 * @visibility public
 * @example Classifying ON COMMIT of a temporary table
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TEMP TABLE t(id INTEGER) ON COMMIT DELETE ROWS')->tables[0];
 *     $table->properties->onCommit // => \SqlSemantics\Schema\Table\CommitAction::DeleteRows
 */
enum CommitAction: string
{
    case PreserveRows = 'preserve-rows';
    case DeleteRows = 'delete-rows';
    case Drop = 'drop';
}
