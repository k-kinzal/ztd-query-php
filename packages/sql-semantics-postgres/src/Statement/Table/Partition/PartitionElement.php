<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * One column or expression of a partition key, with its collation and operator class.
 *
 * Mirrors PostgreSQL's `PartitionElem`. The key is derived where the columns
 * of the table are visible.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading a partition key
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a text) PARTITION BY LIST (a COLLATE "C" text_ops)');
 *     $create->statement->partitioning->elements[0]->operatorClass->last()->value // => 'text_ops'
 */
final class PartitionElement implements Clause
{
    use Snapshot;

    /**
     * @param ColumnKey|ExpressionKey $key The column or expression
     * @param DottedName|null $collation The collation
     * @param DottedName|null $operatorClass The operator class
     */
    public function __construct(public readonly ColumnKey|ExpressionKey $key, public readonly ?DottedName $collation = null, public readonly ?DottedName $operatorClass = null)
    {
    }

    /**
     * Derives the key.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->key, $environment);
    }

    /**
     * Writes the key, the collation and the operator class.
     */
    public function render(Output $out): void
    {
        $out->node($this->key);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
        $out->node($this->operatorClass);
    }
}
