<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UnknownHistogramColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn>
 */
final class UnknownHistogramColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $column Generated constructor operand
     */
    public function __construct(ErisGenerator $column)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn(column: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $column
        ));
    }

    /**
     * Generates the complete dependency sequence that owns this command condition.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Statement>>
     */
    public static function statements(): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Server\Problem\UnknownHistogramColumn $problem): array {
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7', searchPath: new \SqlSemantics\Contract\SearchPath('pbt'));
            $table = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('histogram_target'));
            $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('present_' . $problem->column->value)), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
            $command = new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable(false, [new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable($table)], new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\UpdateHistogram([$problem->column]));
            $create = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable($table, [$column]));
            return [$create, (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context([$create]), $command)];
        }, new self(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator()));
    }

}
