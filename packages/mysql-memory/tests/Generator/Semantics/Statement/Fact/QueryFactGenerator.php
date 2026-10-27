<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Fact;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes QueryFact values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Fact\QueryFact>
 */
final class QueryFactGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Fact\QueryFact> */
    use Composition;

    /**
     * @param ErisGenerator<array<array-key, object>> $projection Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Comparison> $names Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Scalar>>|null $aggregates Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Fact\Diagnostic>>|null $diagnostics Generated constructor operand
     */
    public function __construct(ErisGenerator $projection, ErisGenerator $names, ?ErisGenerator $aggregates = null, ?ErisGenerator $diagnostics = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Fact\QueryFact {
            return new \SqlSemantics\Statement\Fact\QueryFact(projection: $parts[0], names: $parts[1], aggregates: $parts[2], diagnostics: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $projection,
            $names,
            $aggregates ?? Generators::constant([]),
            $diagnostics ?? Generators::constant([])
        ));
    }
}
