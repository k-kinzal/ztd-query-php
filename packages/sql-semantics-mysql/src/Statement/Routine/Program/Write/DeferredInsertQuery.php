<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Query;

/**
 * A stored query write retaining its destination and a deferred read source.
 *
 * A skipped instruction does not open its destination or prepare the source.
 * The source's fixed read constraint cannot inhabit ordinary InsertQuery.
 *
 * @example Refusing to defer an ordinary query source
 *     $head = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))));
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery($head, $query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility public
 */
final class DeferredInsertQuery implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<Assignment> The duplicate-key assignments in written order
     */
    public readonly array $onDuplicate;

    /**
     * @param InsertInto $into The complete write head
     * @param Query $source The complete retained read, including unused definitions
     * @param list<Assignment> $onDuplicate The duplicate-key assignments
     */
    public function __construct(public readonly InsertInto $into, public readonly Query $source, array $onDuplicate = [], public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        $this->onDuplicate = Check::listOf($onDuplicate, Assignment::class, 'ON DUPLICATE KEY UPDATE holds assignments.');
        Check::input(!$into->replace || $this->onDuplicate === [], 'REPLACE has no ON DUPLICATE KEY UPDATE.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->values($source) === null, 'A deferred read source is not an ordinary VALUES write.');
        Check::input((new ReadColumns())->problem($source) !== null, 'A deferred query write retains an intrinsic read-column condition.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives target and source dependencies in their distinct scopes.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        (new InsertFacts())->query($this, $derivation, $scope->environment);
    }

    /**
     * Describes the read constraint after preceding write dependencies bind.
     */
    public function problem(): Diagnostic
    {
        $problem = (new ReadColumns())->problem($this->source);
        Check::invariant($problem !== null, 'A deferred query write retains a read condition.');
        return $problem;
    }

    /**
     * Writes every retained write and source operand.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->node($this->source);
        if ($this->onDuplicate !== []) {
            $out->keyword('ON', 'DUPLICATE', 'KEY', 'UPDATE')->list($this->onDuplicate);
        }
    }
}
