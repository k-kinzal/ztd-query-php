<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class SystemCatalogExecutionTest extends TestCase
{
    #[DataProvider('providerVersions')]
    public function testMetadataAndZeroLimitDoNotRequireUnrequestedRows(string $version): void
    {
        $session = (new Instance($version))->connect();
        $result = $session->query('SELECT THREAD_ID FROM performance_schema.threads LIMIT 0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount(1, $result->columns);
        self::assertSame('THREAD_ID', $result->columns[0]->name);
        self::assertSame([], $result->rows);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerVersions(): iterable
    {
        foreach (\Container\MySqlRelease::versions() as $version) {
            yield $version => [$version];
        }
    }


    #[DataProvider('providerGeneratedCatalogs')]
    public function testGeneratedCatalogMetadataIsConsumedByShowColumns(\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables $catalog, \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns $statement): void
    {
        $instance = new Instance('8.4.7');
        $instance->dictionary->system = new \MySqlMemory\System\SystemSchemas($instance, \SqlSemantics\Contract\GrammarRelease::MySql847, $catalog);
        $reply = (new \MySqlMemory\Session\Execution($instance->connect()))->statement($statement);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame(array_map(static fn ($column): string => $column->comment, $catalog->tables[0]->columns), array_column($reply->rows, 8));
    }

    /**
     * @return iterable<array{\SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables, \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns}>
     */
    public static function providerGeneratedCatalogs(): iterable
    {
        $random = new \Eris\Random\RandomRange(new \Eris\Random\MersenneTwister());
        $random->seed(1983);
        $generator = new \Tests\Generator\MySql\Statement\Table\Catalog\SystemTablesGenerator(\SqlSemantics\Contract\GrammarRelease::MySql847);
        do {
            $catalog = $generator(8, $random)->unbox();
        } while ($catalog->tables === []);
        $table = $catalog->tables[0];
        $statement = new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns(new \SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name($table->name), new \SqlSemantics\Statement\Identifier\Name($table->schema))));
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
        yield [$catalog, (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $statement)];
    }
}
