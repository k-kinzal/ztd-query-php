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
     * A bare SET target that is neither a local nor a system variable of the release follows
     * the body conditions (`BodyNames`). MySQL 8.0 and later report it once the SET statement is
     * read, after the notices of its values; 5.6 and 5.7 report it before reading the value
     * (`CREATE PROCEDURE p() SET missing=WEIGHT_STRING('value' AS CHAR(1.5))` answers the fractional
     * precision on 8.4 and the unknown variable on 5.7).
     *
     * @throws RejectedInput When the body has a definition condition or assigns a name nothing declares
     */
    public function checked(Node $tail, QualifiedName $name, ProgramKind $kind, \SqlSemantics\Statement\Node $body, ?ParameterList $parameters, array $characteristics, ?Account $definer, \SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource $source, ?DeclaredName $returns = null, ?CollationName $collation = null, array $typeEnds = []): void
    {
        $sources = $this->lowering->origins->publish();
        $refusal = (new BodyConditions())->refusal($body, $kind, $name, $parameters, $characteristics, $source->bodyRefusalOffset, $sources);
        if ($refusal === null) {
            $locals = array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Routine\Parameter $parameter): \SqlSemantics\Statement\Identifier\Name => $parameter->name, $parameters->parameters ?? []);
            $unknown = (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyNames())->unknown($body, $locals, \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::of($this->lowering->profile->grammar));
            if ($unknown === null) {
                return;
            }
            $legacy = in_array($this->lowering->profile->grammar, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
            $origin = $sources->of($legacy ? $unknown[2] : $unknown[1]);
            $end = $origin === null ? ((new SourceRanges())->span($tail)[1] ?? 0) : ($legacy ? $origin->offset : $origin->offset + $origin->length);
            throw new RejectedInput(new \SqlSemantics\Platform\MySql\Statement\Variable\Problem\UnknownSystemVariable($unknown[0]->value), $end, true, $sources);
        }
        [$problem, $at, $aborts] = $refusal;
        $origin = $aborts ? $sources->of($at) : null;
        $end = $origin === null ? ((new SourceRanges())->span($tail)[1] ?? 0) : $origin->offset + $origin->length;
        throw new RejectedInput(new RefusedProgramBody($name, $kind, $problem, $definer, $parameters, $returns, $collation, $typeEnds, $source->nameEnd), $end, $aborts, $sources);
    }
}
