<?php

declare(strict_types=1);

namespace Tests\Integration\SqlFaker;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\GrammarCoverage;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\MySqlProvider;
use SqlFaker\PostgreSqlProvider;
use SqlFaker\SqliteProvider;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[\PHPUnit\Framework\Attributes\Large]
#[CoversNothing]
final class ProviderCoverageTest extends TestCase
{
    /**
     * @return list<array{class-string<MySqlProvider|PostgreSqlProvider|SqliteProvider>, string}>
     */
    public static function providerDialects(): array
    {
        return [[MySqlProvider::class, 'mysql-8.4.7'], [PostgreSqlProvider::class, 'pg-17.2'], [SqliteProvider::class, 'sqlite-3.47.2']];
    }

    /**
     * @param class-string<MySqlProvider|PostgreSqlProvider|SqliteProvider> $class
     */
    #[DataProvider('providerDialects')]
    public function testCoverageAndRestoredHistoryDoNotChangeDeterministicSql(string $class, string $version): void
    {
        $plan = GenerationPlan::all()->requiringNonEmpty()->withExpansionBudget(100)->withChoiceBytes("\x01\x02\x03", "\x07\x09");
        $plain = new $class(Factory::create(), $version);
        $expected = $plain->generate($plan);
        $directory = CoverageFixture::directory();
        $coverage = new GrammarCoverage($directory);
        $observed = new $class(Factory::create(), $version, $coverage);
        self::assertSame($expected, $observed->generate($plan));
        $coverage->flush();
        $snapshot = $coverage->snapshot();
        unset($observed, $coverage);
        gc_collect_cycles();
        $restored = new GrammarCoverage($directory);
        $provider = new $class(Factory::create(), $version, $restored);
        self::assertSame(0, $restored->snapshot()['current']['reached']);
        self::assertSame($snapshot['cumulative'], $restored->snapshot()['cumulative']);
        self::assertSame($expected, $provider->generate($plan));
        $provider->generate(GenerationPlan::all()->requiringNonEmpty()->withExpansionBudget(30)->withChoiceBytes('', ''));
        self::assertSame($expected, $provider->generate($plan));
        unset($provider, $restored);
        gc_collect_cycles();
        CoverageFixture::remove($directory);
    }

    public function testSelectOnlyUsageKeepsOtherStatementAlternativesInTheDenominator(): void
    {
        $coverage = new GrammarCoverage();
        $provider = new SqliteProvider(Factory::create(), coverage: $coverage);
        $before = $coverage->snapshot()['current']['total'];
        $provider->selectStatement(maxDepth: 3);
        self::assertSame($before, $coverage->snapshot()['current']['total']);
        self::assertGreaterThan(0, count($coverage->snapshot()['current']['notReachedIds']));
        self::assertLessThan($before, $coverage->snapshot()['current']['reached']);
    }
}
