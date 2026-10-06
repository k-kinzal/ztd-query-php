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
 * INSERT or REPLACE of one row written as SET assignments, with the optional row alias and ON DUPLICATE KEY UPDATE.
 *
 * Rule: MYSQL-INSERT-SET-001. The facts follow MYSQL-INSERT-001; the
 * assigned columns are the written columns and must be distinct.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the assignments of an INSERT ... SET
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT INTO t SET a = 1, b = a + 1');
 *     [count($insert->statement->assignments), $insert->toString()] // => [2, 'INSERT INTO t SET a = 1, b = a + 1']
 */
final class InsertSet implements Statement
{
    use Snapshot;

    /**
     * @var list<Assignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * @var list<Assignment> The assignments of ON DUPLICATE KEY UPDATE in written order
     */
    public readonly array $onDuplicate;

    /**
     * @param InsertInto $into The head: verb, modifiers and table; no column list
     * @param list<Assignment> $assignments The assignments of the row; at least one
     * @param RowAlias|null $alias The alias of the new row
     * @param list<Assignment> $onDuplicate The assignments of ON DUPLICATE KEY UPDATE
     * @throws InvalidConstruction When the head has a column list, there is no assignment, or REPLACE has an alias or ON DUPLICATE KEY UPDATE
     */
    public function __construct(public readonly InsertInto $into, array $assignments, public readonly ?RowAlias $alias = null, array $onDuplicate = [])
    {
        Check::input($into->columns === null, 'INSERT ... SET names its columns in the assignments.');
        $this->assignments = Check::listOf($assignments, Assignment::class, 'SET holds at least one assignment.', 1);
        $this->onDuplicate = Check::listOf($onDuplicate, Assignment::class, 'ON DUPLICATE KEY UPDATE holds assignments.');
        Check::input(!$into->replace || ($alias === null && $this->onDuplicate === []), 'REPLACE has neither a row alias nor ON DUPLICATE KEY UPDATE.');
    }

    /**
     * Derives the written table, the assignments and the assignments of ON DUPLICATE KEY UPDATE; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new InsertFacts())->set($this, $derivation, $derivation->environment());
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->keyword('SET')->list($this->assignments)->node($this->alias);
        if ($this->onDuplicate !== []) {
            $out->keyword('ON', 'DUPLICATE', 'KEY', 'UPDATE')->list($this->onDuplicate);
        }
    }
}
