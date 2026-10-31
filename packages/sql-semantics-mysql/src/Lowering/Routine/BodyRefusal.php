<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Refuses a stored program definition whose completed body the server cannot store.
 *
 * The refusal carries the typed header read so far. A condition the server finds
 * while parsing stops reading at its instruction; a body-wide restriction is
 * reported once the whole definition is read.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyRefusal
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Refuses the definition when its body has a definition condition.
     *
     * @param Node $tail The parser node of the whole definition tail
     * @param list<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic> $characteristics The routine characteristics in written order
     * @param list<int> $typeEnds Completion offsets of the parameter and return declarations
     * @throws RejectedInput When the body has a definition condition
     */
    public function checked(Node $tail, QualifiedName $name, ProgramKind $kind, \SqlSemantics\Statement\Node $body, ?ParameterList $parameters, array $characteristics, ?Account $definer, \SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource $source, ?DeclaredName $returns = null, ?CollationName $collation = null, array $typeEnds = []): void
    {
        $sources = $this->lowering->origins->publish();
        $refusal = (new BodyConditions())->refusal($body, $kind, $name, $parameters, $characteristics, $source->bodyRefusalOffset, $sources);
        if ($refusal === null) {
            return;
        }
        [$problem, $at, $aborts] = $refusal;
        $origin = $aborts ? $sources->of($at) : null;
        $end = $origin === null ? ((new SourceRanges())->span($tail)[1] ?? 0) : $origin->offset + $origin->length;
        throw new RejectedInput(new RefusedProgramBody($name, $kind, $problem, $definer, $parameters, $returns, $collation, $typeEnds, $source->nameEnd), $end, $aborts, $sources);
    }
}
