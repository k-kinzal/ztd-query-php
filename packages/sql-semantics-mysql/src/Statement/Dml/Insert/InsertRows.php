<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * INSERT or REPLACE of rows written in a VALUES clause, with the optional row alias and ON DUPLICATE KEY UPDATE.
 *
 * Rule: MYSQL-INSERT-ROWS-001. The facts follow MYSQL-INSERT-001 with the
 * rows of VALUES as the source; VALUE is a synonym of VALUES.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the rows of an INSERT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("INSERT IGNORE INTO t (a, b) VALUE (1, 'x'), (2, DEFAULT) ON DUPLICATE KEY UPDATE b = VALUES(b)");
 *     [count($insert->statement->rows), count($insert->statement->onDuplicate), $insert->toString()] // => [2, 1, "INSERT IGNORE INTO t (a, b) VALUES (1, 'x'), (2, DEFAULT) ON DUPLICATE KEY UPDATE b = VALUES(b)"]
 */
final class InsertRows implements Statement
{
    use Snapshot;

    /**
     * @var list<InsertedRow> The rows in written order
     */
    public readonly array $rows;

    /**
     * @var list<Assignment> The assignments of ON DUPLICATE KEY UPDATE in written order
     */
    public readonly array $onDuplicate;

    /**
     * @param InsertInto $into The head: verb, modifiers, table and column list
     * @param list<InsertedRow> $rows The rows; at least one
     * @param RowAlias|null $alias The alias of the new row
     * @param list<Assignment> $onDuplicate The assignments of ON DUPLICATE KEY UPDATE
     * @throws InvalidConstruction When there is no row, or REPLACE has an alias or ON DUPLICATE KEY UPDATE
     */
    public function __construct(public readonly InsertInto $into, array $rows, public readonly ?RowAlias $alias = null, array $onDuplicate = [])
    {
        $this->rows = Check::listOf($rows, InsertedRow::class, 'VALUES holds at least one row.', 1);
        $this->onDuplicate = Check::listOf($onDuplicate, Assignment::class, 'ON DUPLICATE KEY UPDATE holds assignments.');
        Check::input(!$into->replace || ($alias === null && $this->onDuplicate === []), 'REPLACE has neither a row alias nor ON DUPLICATE KEY UPDATE.');
    }

    /**
     * Derives the written table, the columns, the rows and the assignments; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new InsertFacts())->rows($this, $derivation, $derivation->environment());
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->keyword('VALUES')->list($this->rows)->node($this->alias);
        if ($this->onDuplicate !== []) {
            $out->keyword('ON', 'DUPLICATE', 'KEY', 'UPDATE')->list($this->onDuplicate);
        }
    }
}
