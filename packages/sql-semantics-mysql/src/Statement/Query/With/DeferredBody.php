<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\With;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\Routine\Selection\SelectionFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Operand\RefusedSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Query;

/**
 * A query template whose retained operands are checked when a read reaches it.
 *
 * CTE definitions are not themselves read statements. An unused definition can
 * retain a row where a scalar would be required on reference. QueryRoot retains
 * this template and its read condition; unused definitions remain inactive.
 * No source SQL, parser node or substitute scalar is kept.
 *
 * @visibility public
 * @example Keeping a definition outside the statement role
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($row)]);
 *     new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($selection) instanceof \SqlSemantics\Statement\Statement // => false
 */
final class DeferredBody implements Query
{
    use \SqlSemantics\Statement\Semantics\QuerySemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param DeferredSelection $selection All projection, input and clause operands
     */
    public function __construct(public readonly DeferredSelection $selection, ?QueryFact $queryInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($queryInformation !== null) {
            $this->initializeQueryInformation($queryInformation);
        }
    }

    /**
     * Binds the definition in its lexical environment, preserving invalid output types.
     */
    public function deriveQuery(Derivation $derivation, Environment $outer): QueryFact
    {
        $fields = (new SelectionFacts())->derive($this->selection, $derivation, new ProgramScope($outer));
        return new QueryFact($fields, $derivation->context->columnNames);
    }

    /**
     * Describes the first intrinsic read constraint without opening its dependencies.
     */
    public function problem(): Diagnostic
    {
        return (new RefusedSelection($this->selection))->problem();
    }

    /**
     * Writes the complete retained definition.
     */
    public function render(Output $out): void
    {
        $out->node($this->selection);
    }
}
