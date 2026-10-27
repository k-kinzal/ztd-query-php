<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Catalog;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SystemTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables>
 */
final class SystemTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable>> $tables Generated constructor operand
     */
    public function __construct(ErisGenerator $tables)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables(tables: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $tables
        ));
    }

    /**
     * Generates a system-table catalog and its dependent SHOW COLUMNS request.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables, \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns}>
     */
    public static function inspections(): ErisGenerator
    {
        return Generators::map(static function (\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables $catalog): array {
            $table = $catalog->tables[0];
            $request = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable($table->declaration->name));
            $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '8.4.7');
            $bound = \SqlSemantics\Facade\Semantics::fromStatement($semantics->context([$table->declaration]), $request);
            \SqlSemantics\Diagnostic\Check::invariant($bound instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns, 'The generated catalog inspection remains SHOW COLUMNS.');
            return [$catalog, $bound];
        }, new self(Generators::vector(1, SystemTableGenerator::catalog())));
    }

}
