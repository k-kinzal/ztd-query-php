<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AbsentTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable>
 */
final class AbsentTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Generates DROP IF EXISTS against its explicitly absent target.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\DropTable>
     */
    public static function statements(): ErisGenerator
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('pbt')));
        return \Tests\Generator\Operands::bind(new self($names), static function (\SqlSemantics\Platform\MySql\Statement\Alter\AbsentTable $absent): DropTableGenerator {
            $target = new TargetTableGenerator(Generators::constant($absent->name), relationInformation: new \Tests\Generator\Semantics\Statement\Fact\RelationFactGenerator(Generators::constant(new \SqlSemantics\Statement\Shape\RowShape([])), Generators::constant($absent)));
            return new DropTableGenerator(Generators::bool(), Generators::constant(true), Generators::vector(1, $target), statementInformation: new \Tests\Generator\MySql\Statement\StatementSemanticsGenerator(new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(Generators::constant(\SqlSemantics\Contract\GrammarRelease::MySql847))));
        });
    }

}
