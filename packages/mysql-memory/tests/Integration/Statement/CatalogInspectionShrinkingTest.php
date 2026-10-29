<?php

declare(strict_types=1);

namespace Tests\Integration\Statement;

use Eris\Quantifier\ForAll;
use Eris\Random\MersenneTwister;
use Eris\Random\RandomRange;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\System\SystemSchemas;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;
use Tests\Eris\ShrinkerFactory;
use Tests\Generator\MySql\Statement\Table\Catalog\SystemTablesGenerator;

#[CoversNothing]
#[Large]
final class CatalogInspectionShrinkingTest extends TestCase
{
    public function testShrinkingKeepsTheInstalledCatalogAndItsReadTogether(): void
    {
        $random = new RandomRange(new MersenneTwister());
        $random->seed(1977);
        $quantifier = new ForAll([SystemTablesGenerator::inspections()], 100, new ShrinkerFactory(['timeLimit' => null]), 'multiple', $random);
        $attempts = 0;
        $last = [];
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Intentional catalog counterexample');
        try {
            $quantifier->then(static function (array $sample) use (&$attempts, &$last): void {
                [$catalog, $statement] = $sample;
                self::assertInstanceOf(SystemTables::class, $catalog);
                self::assertInstanceOf(ShowColumns::class, $statement);
                $instance = new Instance('8.4.7');
                $instance->dictionary->system = new SystemSchemas($instance, GrammarRelease::MySql847, $catalog);
                $session = $instance->connect();
                $reply = (new Execution($session))->statement($statement);
                self::assertInstanceOf(ResultSet::class, $reply);
                $last = array_column($reply->rows, 8);
                self::assertSame(array_map(static fn ($column): string => $column->comment, $catalog->tables[0]->columns), $last);
                $attempts++;
                self::fail('Intentional catalog counterexample');
            });
        } finally {
            self::assertGreaterThan(1, $attempts);
            self::assertSame(['generated comment 0'], array_values(array_unique($last)));
        }
    }
}
