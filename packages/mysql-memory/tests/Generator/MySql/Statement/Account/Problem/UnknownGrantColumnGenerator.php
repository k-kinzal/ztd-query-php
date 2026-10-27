<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnknownGrantColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn>
 */
final class UnknownGrantColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $table Generated constructor operand
     */
    public function __construct(ErisGenerator $column, ErisGenerator $table)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn(column: $parts[0], table: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $column,
            $table
        ));
    }

    /**
     * Generates the complete dependency sequence that owns this command condition.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Statement>>
     */
    public static function statements(): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn $problem): array {
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7', searchPath: new \SqlSemantics\Contract\SearchPath('pbt'));
            $table = $problem->table;
            $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('present_' . $problem->column->value)), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
            $command = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges(
                [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Select, [$problem->column])],
                \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table,
                new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel($table),
                [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())],
            );
            $create = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable($table, [$column]));
            return [$create, (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context([$create]), $command)];
        }, new self(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(), new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator())));
    }

}
