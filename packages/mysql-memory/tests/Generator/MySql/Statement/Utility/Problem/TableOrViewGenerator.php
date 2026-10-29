<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TableOrView values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView>
 */
final class TableOrViewGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Generates an inspection whose result columns depend on an absent object kind.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateTable>
     */
    public static function statements(): ErisGenerator
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('pbt')));
        return \Tests\Generator\Operands::bind(new self($names), static function (\SqlSemantics\Platform\MySql\Statement\Utility\Problem\TableOrView $missing): \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateTableGenerator {
            $input = new \Tests\Generator\MySql\Statement\Utility\Show\InspectedTableGenerator(Generators::constant($missing->name), relationInformation: new \Tests\Generator\Semantics\Statement\Fact\RelationFactGenerator(Generators::constant(new \SqlSemantics\Statement\Shape\RowShape([], [$missing])), new \Tests\Generator\Semantics\Statement\Reference\Table\UndeclaredTableGenerator(new \Tests\Generator\Semantics\Statement\Reference\Missing\UndeclaredRelationGenerator(Generators::constant($missing->name)))));
            $output = new \Tests\Generator\Semantics\Statement\Fact\QueryFactGenerator(\Tests\Generator\Operands::vector(1, new \Tests\Generator\Semantics\Statement\Shape\OpenStarGenerator(Generators::constant([$missing]))), Generators::constant(\SqlSemantics\Statement\Identifier\Comparison::AsciiInsensitive));
            return new \Tests\Generator\MySql\Statement\Utility\Show\Schema\ShowCreateTableGenerator($input, new \Tests\Generator\MySql\Statement\StatementSemanticsGenerator(new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(Generators::constant(\SqlSemantics\Contract\GrammarRelease::MySql847)), output: $output));
        });
    }

}
