<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Generator\MySql\Statement\Table\Catalog\SystemTablesGenerator;

/**
 * Executes system-table metadata requests composed from the selected declaration.
 */
#[CoversNothing]
#[Large]
final class CatalogInspectionProperties extends TestCase
{
    use TestTrait;

    /**
     * Every generated catalog column remains visible, in declaration order.
     */
    #[Test]
    public function satisfiesGeneratedCatalogInspectionReturnsItsColumns(): void
    {
        $this->forAll(SystemTablesGenerator::inspections())->then(/** @param array{\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables, \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns} $input */
            static function (array $input): void {
                [$catalog, $statement] = $input;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables::class, $catalog);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns::class, $statement);
                $table = $catalog->tables[0];
                $session = (new Instance('8.4.7'))->connect();
                $session->text = 'Generated system catalog inspection without SQL input';
                $reply = (new Execution($session))->statement($statement);
                self::assertInstanceOf(ResultSet::class, $reply);
                self::assertSame(array_map(static fn ($column): string => $column->name, $table->columns), array_column($reply->rows, 0));
                self::assertSame(array_map(static fn ($column): string => $column->columnType, $table->columns), array_column($reply->rows, 1));
                self::assertSame(array_map(static fn ($column): string => $column->listedNullable ? 'YES' : 'NO', $table->columns), array_column($reply->rows, 2));
                self::assertSame('Generated system catalog inspection without SQL input', $session->text);
            }
        );
    }
}
