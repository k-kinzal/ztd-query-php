<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Level;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AbsentGrantTable values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable>
 */
final class AbsentGrantTableGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     */
    public function __construct(ErisGenerator $name)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable(name: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $name
        ));
    }
    /**
     * Grants CREATE on the generated name before the table exists.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges>
     */
    public static function statements(): ErisGenerator
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(Generators::constant('pbt')));
        return \Tests\Generator\Operands::bind(new self($names), static function (\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable $absent): \Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator {
            $level = new ObjectLevelGenerator(Generators::constant($absent->name), relationInformation: new \Tests\Generator\Semantics\Statement\Fact\RelationFactGenerator(Generators::constant(new \SqlSemantics\Statement\Shape\RowShape([])), Generators::constant($absent)));
            return new \Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator(
                Generators::vector(1, new \Tests\Generator\MySql\Statement\Account\Privilege\Item\StaticPrivilegeGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Create))),
                Generators::constant(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table),
                $level,
                Generators::vector(1, new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(new \Tests\Generator\MySql\Statement\Name\CurrentUserGenerator())),
                statementInformation: new \Tests\Generator\MySql\Statement\StatementSemanticsGenerator(new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(Generators::constant(\SqlSemantics\Contract\GrammarRelease::MySql847))),
            );
        });
    }

}
