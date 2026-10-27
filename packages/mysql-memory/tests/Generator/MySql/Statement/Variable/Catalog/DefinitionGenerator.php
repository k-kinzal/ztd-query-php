<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable\Catalog;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Definition values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition>
 */
final class DefinitionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach> $reach Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape> $shape Generated constructor operand
     * @param ErisGenerator<string|int|null> $default Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability> $writability Generated constructor operand
     * @param ErisGenerator<int|null> $minimum Generated constructor operand
     * @param ErisGenerator<int|null> $maximum Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain> $domain Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $reach, ErisGenerator $shape, ErisGenerator $default, ErisGenerator $writability, ErisGenerator $minimum, ErisGenerator $maximum, ErisGenerator $domain)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition(name: $parts[0], reach: $parts[1], shape: $parts[2], default: $parts[3], writability: $parts[4], minimum: $parts[5], maximum: $parts[6], domain: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $reach,
            $shape,
            $default,
            $writability,
            $minimum,
            $maximum,
            $domain
        ));
    }

    /**
     * Composes one catalog entry while preserving its release-specific scope and domain.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition>
     */
    public static function catalog(): ErisGenerator
    {
        $entries = array_values(\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::of(\SqlSemantics\Contract\GrammarRelease::MySql847)->definitions);
        return \Tests\Generator\Operands::bind(Generators::elements($entries), static fn (\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition $entry): self => new self(
            Generators::constant($entry->name),
            ReachGenerator::selecting([$entry->reach]),
            ValueShapeGenerator::selecting([$entry->shape]),
            Generators::constant($entry->default),
            WritabilityGenerator::selecting([$entry->writability]),
            Generators::constant($entry->minimum),
            Generators::constant($entry->maximum),
            Generators::constant($entry->domain),
        ));
    }

}
