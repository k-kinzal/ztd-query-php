<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Definition;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use SqlSemantics\Platform\MySql\Statement\Routine\Definition\DefinitionSource;
use Tests\Generator\Composition;

/**
 * Composes optional definition boundaries without supplying SQL meaning.
 *
 * @implements ErisGenerator<DefinitionSource>
 */
final class DefinitionSourceGenerator implements ErisGenerator
{
    /** @use Composition<DefinitionSource> */
    use Composition;

    /**
     * @param ErisGenerator<int>|null $nameEnd Generated name completion
     * @param ErisGenerator<list<int>>|null $typeEnds Generated parameter and return completions
     * @param ErisGenerator<int|null>|null $bodyRefusalOffset Generated language refusal boundary
     */
    public function __construct(?ErisGenerator $nameEnd = null, ?ErisGenerator $typeEnds = null, ?ErisGenerator $bodyRefusalOffset = null)
    {
        $this->delegate = Generators::map(static fn (array $parts): DefinitionSource => new DefinitionSource($parts[0], $parts[1], $parts[2]), \Tests\Generator\Operands::tuple(
            $nameEnd ?? Generators::choose(0, 1000),
            $typeEnds ?? \Tests\Generator\Operands::seq(Generators::choose(0, 1000)),
            $bodyRefusalOffset ?? Generators::oneOf(Generators::constant(null), Generators::choose(0, 1000)),
        ));
    }
}
