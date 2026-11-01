<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Explainable;
use SqlSemantics\Rendering\Output;

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
 *     [count($insert->assignments), $insert->toString()] // => [2, 'INSERT INTO t SET a = 1, b = a + 1']
 */
final class InsertSet extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Explainable, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * @var list<Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> The assignments of ON DUPLICATE KEY UPDATE in written order
     */
    public readonly array $onDuplicate;

    /**
     * @param InsertInto $into The head: verb, modifiers and table; no column list
     * @param list<Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> $assignments The assignments of the row; at least one
     * @param RowAlias|null $alias The alias of the new row
     * @param list<Assignment|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment> $onDuplicate The assignments of ON DUPLICATE KEY UPDATE
     * @throws InvalidConstruction When the head has a column list, there is no assignment, or REPLACE has an alias or ON DUPLICATE KEY UPDATE
     */
    public function __construct(public readonly InsertInto $into, array $assignments, public readonly ?RowAlias $alias = null, array $onDuplicate = [], ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($into->columns === null, 'INSERT ... SET names its columns in the assignments.');
        $items = Check::listOf($assignments, \SqlSemantics\Statement\Node::class, 'SET holds at least one assignment.', 1);
        foreach ($items as $item) {
            Check::input($item instanceof Assignment || $item instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment, 'INSERT assignments retain their column and value roles.');
        }
        $this->assignments = $items;
        $items = Check::listOf($onDuplicate, \SqlSemantics\Statement\Node::class, 'ON DUPLICATE KEY UPDATE holds assignments.');
        foreach ($items as $item) {
            Check::input($item instanceof Assignment || $item instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment, 'INSERT assignments retain their column and value roles.');
        }
        $this->onDuplicate = $items;
        Check::input(!$into->replace || ($alias === null && $this->onDuplicate === []), 'REPLACE has neither a row alias nor ON DUPLICATE KEY UPDATE.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Reports whether an assignment retains a non-scalar preparation condition.
     */
    public function hasExecutionCondition(): bool
    {
        return array_filter([...$this->assignments, ...$this->onDuplicate], static fn ($assignment): bool => $assignment instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredColumnAssignment) !== [];
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
