<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable\Catalog;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SystemVariables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables>
 */
final class SystemVariablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables> */
    use Composition;

    /**
     * @param ErisGenerator<array<string, \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition>> $definitions Generated constructor operand
     */
    public function __construct(ErisGenerator $definitions)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables {
            return new \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables(definitions: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $definitions
        ));
    }

    /**
     * Generates a catalog and a read with the scope and type of its selected definition.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables, \SqlSemantics\Platform\MySql\Statement\QueryRoot}>
     */
    public static function readQueries(): ErisGenerator
    {
        $catalogs = new self(Generators::map(static fn (\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition $entry): array => [$entry->name => $entry], DefinitionGenerator::catalog()));
        return \Tests\Generator\Operands::bind($catalogs, static function (\SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables $catalog): ErisGenerator {
            $entry = array_values($catalog->definitions)[0];
            $variables = new \Tests\Generator\MySql\Statement\Variable\SystemVariableGenerator(
                new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant($entry->name), Generators::constant(null)),
                \Tests\Generator\MySql\Statement\Variable\VariableScopeGenerator::selecting([$entry->reach === \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach::Session ? \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Session : \SqlSemantics\Platform\MySql\Statement\Variable\VariableScope::Global]),
                scalarInformation: new \Tests\Generator\Semantics\Statement\Fact\ScalarFactGenerator(
                    new \Tests\Generator\Semantics\Statement\Type\KnownGenerator(Generators::constant($entry->domain)),
                    \Tests\Generator\Semantics\Statement\Type\NullabilityGenerator::selecting([\SqlSemantics\Statement\Type\Nullability::Nullable]),
                ),
            );
            return \Tests\Generator\Operands::tuple(Generators::constant($catalog), \Tests\Generator\MySql\Statement\QueryRootGenerator::namedScalar($variables));
        });
    }

}
