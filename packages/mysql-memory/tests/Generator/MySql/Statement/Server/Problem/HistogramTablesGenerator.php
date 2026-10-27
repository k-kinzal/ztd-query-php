<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes HistogramTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables>
 */
final class HistogramTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables> */
    use Composition;

    /**
     * @param ErisGenerator<int> $count Generated constructor operand
     */
    public function __construct(ErisGenerator $count)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables(count: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $count
        ));
    }

    /**
     * Generates the complete dependency sequence that owns this command condition.
     *
     * @return ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Statement>>
     */
    public static function statements(): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Server\Problem\HistogramTables $problem): array {
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7', searchPath: new \SqlSemantics\Contract\SearchPath('pbt'));
            $tables = array_map(static fn (int $index): \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable => new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('target_' . $index))), range(1, $problem->count));
            return [(new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($semantics->context(), new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\AnalyzeTable(false, $tables, new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\DropHistogram([new \SqlSemantics\Statement\Identifier\Name('column')])))];
        }, new self(Generators::choose(2, 4)));
    }

}
