<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored INSERT or REPLACE retaining every value, target and duplicate-key clause.
 *
 * Written widths conflict or at least one value has a non-scalar or grouped preparation condition. The ordinary write
 * domain remains InsertRows; a skipped stored instruction never prepares this
 * template. Its target and every operand bind together when it is reached.
 *
 * @example Refusing an ordinary write in the deferred role
 *     $into = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))));
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows($into, [new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @visibility public
 */
final class DeferredInsertRows implements ProgramStatement
{
    use Snapshot;

    /**
     * @var non-empty-list<InsertedRow|DeferredInsertedRow> The rows in written order
     */
    public readonly array $rows;

    /**
     * @var list<Assignment|DeferredColumnAssignment> The assignments of ON DUPLICATE KEY UPDATE in written order
     */
    public readonly array $onDuplicate;

    /**
     * @param InsertInto $into The head: verb, modifiers, table and column list
     * @param list<InsertedRow|DeferredInsertedRow> $rows The rows; at least one
     * @param RowAlias|null $alias The alias of the new row
     * @param list<Assignment|DeferredColumnAssignment> $onDuplicate The assignments of ON DUPLICATE KEY UPDATE
     * @throws InvalidConstruction When there is no row, or REPLACE has an alias or ON DUPLICATE KEY UPDATE
     */
    public function __construct(public readonly InsertInto $into, array $rows, public readonly ?RowAlias $alias = null, array $onDuplicate = [])
    {
        $items = [];
        foreach (Check::listOf($rows, \SqlSemantics\Statement\Node::class, 'VALUES holds at least one row.', 1) as $row) {
            Check::input($row instanceof InsertedRow || $row instanceof DeferredInsertedRow, 'A write retains inserted-row operands.');
            $items[] = $row;
        }
        $assignments = [];
        foreach (Check::listOf($onDuplicate, \SqlSemantics\Statement\Node::class, 'ON DUPLICATE KEY UPDATE holds assignments.') as $assignment) {
            Check::input($assignment instanceof Assignment || $assignment instanceof DeferredColumnAssignment, 'An assignment retains its column and value role.');
            $assignments[] = $assignment;
        }
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\InsertWidths())->problem($into, $items) !== null || array_filter($items, static fn ($row): bool => $row instanceof DeferredInsertedRow) !== [] || array_filter($assignments, static fn ($item): bool => $item instanceof DeferredColumnAssignment) !== [], 'A deferred write retains a width or non-scalar or grouped value condition.');
        $this->rows = $items;
        $this->onDuplicate = $assignments;
        Check::input(!$into->replace || ($alias === null && $this->onDuplicate === []), 'REPLACE has neither a row alias nor ON DUPLICATE KEY UPDATE.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the written table, the columns, the rows and the assignments; the statement returns no rows.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new InsertFacts())->rows($this, $derivation, $scope->environment);
    }

    /**
     * Answers the retained value constraint after its write dependencies bind.
     *
     * @throws LogicException If the constructor invariant has been bypassed
     */
    public function problem(): \SqlSemantics\Statement\Fact\Diagnostic
    {
        $width = (new \SqlSemantics\Platform\MySql\Rules\Dml\InsertWidths())->problem($this->into, $this->rows);
        if ($width !== null) {
            return $width;
        }
        foreach ($this->rows as $row) {
            foreach ($row->values as $value) {
                $grouped = (new \SqlSemantics\Platform\MySql\Rules\Dml\Insert\ValueGroups())->problem($value);
                if ($grouped !== null) {
                    return $grouped;
                }
                if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand || $value instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand) {
                    return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$value]);
                }
            }
        }
        foreach ($this->onDuplicate as $assignment) {
            if ($assignment instanceof DeferredColumnAssignment) {
                return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$assignment->value]);
            }
        }
        throw new LogicException('The deferred-write constructor requires a width or non-scalar or grouped value condition.');
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
