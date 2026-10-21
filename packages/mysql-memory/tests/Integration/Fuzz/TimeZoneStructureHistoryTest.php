<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversNothing]
#[Large]
final class TimeZoneStructureHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Expression\Conversion\AtTimeZone}>
     */
    public static function providerConversions(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (M\Expression\Conversion\TimeZone::cases() as $zone) {
                foreach (['column', 'default', 'minimum'] as $role) {
                    $column = new M\Name\ColumnUse(new Name('2024-01-01 01:02:03'));
                    $operand = match ($role) {
                        'column' => $column,
                        'default' => new M\Expression\Access\DefaultOfColumn($column),
                        'minimum' => new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Minimum, [$column]),
                    };
                    yield $release . ':' . $zone->name . ':' . $role => [$release, new M\Expression\Conversion\AtTimeZone($operand, $zone)];
                }
            }
        }
    }

    #[DataProvider('providerConversions')]
    public function testTheSameConversionRunsAfterTheColumnAcquiresItsTimestampRole(string $release, M\Expression\Conversion\AtTimeZone $node): void
    {
        $observation = (new Comparison())->compare($node, new LanguageProfile(GrammarRelease::from('mysql-' . $release)), namedTimeZones: true);
        self::assertTrue($observation['matches'], serialize($observation));
        $history = $observation['transitions'];
        self::assertContains(3998, array_column(array_column(array_column($history, 'native'), 'error'), 0));
        $last = $history[count($history) - 1];
        self::assertSame($observation['sql'], $last['sql']);
        self::assertArrayNotHasKey('error', $last['native']);
        $results = $last['native']['results'];
        self::assertIsArray($results);
        self::assertIsArray($results[0]);
        self::assertSame([['2024-01-01 01:02:03']], $results[0]['rows']);
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerCatalogs(): iterable
    {
        foreach ([GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }

    #[DataProvider('providerCatalogs')]
    public function testTheSameNamedConversionDistinguishesAbsentAndInstalledZoneCatalogs(GrammarRelease $release): void
    {
        $operand = new M\Expression\Access\DefaultOfColumn(new M\Name\ColumnUse(new Name('2024-01-01 01:02:03')));
        $node = new M\Expression\Conversion\AtTimeZone($operand, M\Expression\Conversion\TimeZone::Utc);
        $profile = new LanguageProfile($release);
        $missing = (new Comparison())->compare($node, $profile, namedTimeZones: false);
        $installed = (new Comparison())->compare($node, $profile, namedTimeZones: true);
        self::assertTrue($missing['matches'], serialize($missing));
        self::assertTrue($installed['matches'], serialize($installed));
        $before = $missing['transitions'][count($missing['transitions']) - 1]['native'];
        self::assertSame([1298, 'HY000', "Unknown or incorrect time zone: 'UTC'"], $before['error']);
        $after = $installed['transitions'][count($installed['transitions']) - 1]['native'];
        self::assertArrayNotHasKey('error', $after);
        self::assertIsArray($after['results']);
        self::assertIsArray($after['results'][0]);
        self::assertSame([['2024-01-01 01:02:03']], $after['results'][0]['rows']);
        self::assertSame($missing['sql'], $installed['sql']);
        self::assertSame($operand, $node->operand);
    }
}
