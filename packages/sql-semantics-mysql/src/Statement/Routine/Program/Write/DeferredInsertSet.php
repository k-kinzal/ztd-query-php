<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use LogicException;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Node;

/**
 * A stored INSERT or REPLACE SET retaining a non-scalar assignment and its context.
 *
 * Every target, assignment and duplicate-key operand remains available when the
 * instruction is reached. This template is neither an ordinary Statement nor a
 * replacement VALUES statement.
 *
 * @visibility public
 * @example Retaining a SET assignment until its stored instruction is reached
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SET a=ROW(1,2)');
 *     $operation->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet // => true
 */
final class DeferredInsertSet implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<Assignment|DeferredColumnAssignment> The complete SET list
     */
    public readonly array $assignments;

    /**
     * @var list<Assignment|DeferredColumnAssignment> The complete duplicate-key list
     */
    public readonly array $onDuplicate;

    /**
     * @param InsertInto $into The write head, without a column list
     * @param list<Assignment|DeferredColumnAssignment> $assignments The nonempty SET list
     * @param RowAlias|null $alias The alias of the new row
     * @param list<Assignment|DeferredColumnAssignment> $onDuplicate The duplicate-key assignments
     */
    public function __construct(public readonly InsertInto $into, array $assignments, public readonly ?RowAlias $alias = null, array $onDuplicate = [], public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($into->columns === null, 'INSERT ... SET names its columns in the assignments.');
        $items = [];
        foreach (Check::listOf($assignments, Node::class, 'SET holds at least one assignment.', 1) as $item) {
            Check::input($item instanceof Assignment || $item instanceof DeferredColumnAssignment, 'SET retains assignment roles.');
            $items[] = $item;
        }
        $updates = [];
        foreach (Check::listOf($onDuplicate, Node::class, 'ON DUPLICATE KEY UPDATE holds assignments.') as $item) {
            Check::input($item instanceof Assignment || $item instanceof DeferredColumnAssignment, 'ON DUPLICATE KEY UPDATE retains assignment roles.');
            $updates[] = $item;
        }
        Check::input(array_filter([...$items, ...$updates], static fn ($item): bool => $item instanceof DeferredColumnAssignment) !== [], 'A deferred INSERT SET retains a non-scalar assignment.');
        Check::input(!$into->replace || ($alias === null && $updates === []), 'REPLACE has neither a row alias nor ON DUPLICATE KEY UPDATE.');
        $this->assignments = $items;
        $this->onDuplicate = $updates;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds the destination and every assignment in the appropriate write scope.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new InsertFacts())->set($this, $derivation, $scope->environment);
    }

    /**
     * Answers the retained operand condition after its dependencies have bound.
     *
     * @throws LogicException If the constructor invariant has been bypassed
     */
    public function problem(): Diagnostic
    {
        foreach ([...$this->assignments, ...$this->onDuplicate] as $assignment) {
            if ($assignment instanceof DeferredColumnAssignment) {
                return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$assignment->value]);
            }
        }
        throw new LogicException('A deferred INSERT SET has a non-scalar assignment.');
    }

    /**
     * Writes SET and its retained operands in their original semantic roles.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->keyword('SET')->list($this->assignments)->node($this->alias);
        if ($this->onDuplicate !== []) {
            $out->keyword('ON', 'DUPLICATE', 'KEY', 'UPDATE')->list($this->onDuplicate);
        }
    }
}
