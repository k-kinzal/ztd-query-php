<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\StatementQuery;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Query;

/**
 * A stored query whose fixed read constraint is checked when it is reached.
 *
 * It retains the complete query but is neither an ordinary Statement nor a
 * Scalar. A skipped instruction or unopened cursor does not prepare its rows.
 * Lexical recursive-reference restrictions still apply to the enclosing program
 * declaration; anchor and output-column restrictions wait for invocation.
 *
 * @visibility public
 * @example Keeping an empty VALUES row as a stored instruction
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([])]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery($query) instanceof \SqlSemantics\Statement\Statement // => false
 * @example Refusing to defer an ordinary query
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery([new \SqlSemantics\Platform\MySql\Statement\Query\ValueRow([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')])]);
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery($query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeferredQuery implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Query $query The stored query and every preparation dependency
     */
    public function __construct(public readonly Query $query, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(!$query instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody, 'A single deferred block is a DeferredSelection instruction directly.');
        (new StatementQuery())->check($query);
        Check::input((new ReadColumns())->problem($query) !== null, 'A deferred query retains an intrinsic read-column constraint.');
        (new Generations())->check($this);
    }

    /**
     * Binds the complete query in the stored declaration's scope.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        $derivation->query($this->query, $scope->environment);
    }

    /**
     * Describes the fixed constraint without deciding which dependency fails first.
     */
    public function problem(): Diagnostic
    {
        $problem = (new ReadColumns())->problem($this->query);
        Check::invariant($problem !== null, 'A deferred query retains a read-column constraint.');
        return $problem;
    }

    /**
     * Writes every retained query clause.
     */
    public function render(Output $out): void
    {
        $out->node($this->query);
    }
}
