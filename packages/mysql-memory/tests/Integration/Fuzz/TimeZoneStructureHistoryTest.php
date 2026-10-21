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
}
