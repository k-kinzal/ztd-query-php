<?php

declare(strict_types=1);

namespace Tests\Property\Statement;

use Eris\TestTrait;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
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
    #[DataProvider('providerReleases')]
    public function satisfiesGeneratedCatalogInspectionReturnsItsColumns(GrammarRelease $release): void
    {
        $this->forAll(SystemTablesGenerator::inspections($release))->then(
            static function (array $input) use ($release): void {
                [$catalog, $statement, $query] = $input;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables::class, $catalog);
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns::class, $statement);
                $table = $catalog->tables[0];
                $instance = new Instance(substr($release->value, 6));
                $instance->dictionary->system = new \MySqlMemory\System\SystemSchemas($instance, $release, $catalog);
                $session = $instance->connect();
                $session->text = 'Generated system catalog inspection without SQL input';
                $reply = (new Execution($session))->statement($statement);
                self::assertInstanceOf(ResultSet::class, $reply);
                self::assertSame(array_map(static fn ($column): string => $column->name, $table->columns), array_column($reply->rows, 0));
                self::assertSame(array_map(static fn ($column): string => $column->columnType, $table->columns), array_column($reply->rows, 1));
                self::assertSame(array_map(static fn ($column): string => $column->listedNullable ? 'YES' : 'NO', $table->columns), array_column($reply->rows, 3));
                self::assertSame(array_map(static fn ($column): string => $column->comment, $table->columns), array_column($reply->rows, 8));
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $query);
                $metadata = (new Execution($session))->statement($query);
                self::assertInstanceOf(ResultSet::class, $metadata);
                self::assertSame(array_map(static fn ($column): array => [
                    $column->dimensions->name,
                    $column->dimensions->characters === null ? null : (string) $column->dimensions->characters,
                    $column->dimensions->octets === null ? null : (string) $column->dimensions->octets,
                    $column->dimensions->precision === null ? null : (string) $column->dimensions->precision,
                    $column->dimensions->scale === null ? null : (string) $column->dimensions->scale,
                    $column->dimensions->fraction === null ? null : (string) $column->dimensions->fraction,
                    $column->comment,
                ], $table->columns), $metadata->rows);
                self::assertSame('Generated system catalog inspection without SQL input', $session->text);
            }
        );
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }

}
