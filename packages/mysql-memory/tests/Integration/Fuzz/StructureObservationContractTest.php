<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use Fuzz\Target\EngineReports;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus;
use SqlSemantics\Statement\Identifier\Name;

#[CoversNothing]
#[Large]
final class StructureObservationContractTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, ShowEngineStatus}>
     */
    public static function providerReleases(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach ([new ShowEngineStatus(), new ShowEngineStatus(new Name('InnoDB')), new ShowEngineStatus(new Name('performance_schema'))] as $node) {
                yield $release->value . ':' . ($node->engine->value ?? 'ALL') => [$release, $node];
            }
        }
    }

    #[DataProvider('providerReleases')]
    public function testCompareEngineReportsRetainsOriginalPayloadsAndRecordsTheContract(GrammarRelease $release, ShowEngineStatus $node): void
    {
        $profile = new LanguageProfile($release);
        $comparison = new Comparison();
        $observations = [$comparison->compare($node, $profile), $comparison->compare($node, $profile)];

        self::assertSame(array_fill(0, 2, true), array_column($observations, 'matches'));
        self::assertSame(array_fill(0, 2, [EngineReports::CONTRACT]), array_column($observations, 'contracts'));
        self::assertSame(array_fill(0, 2, []), array_column(array_column(array_column(array_column($observations, 'memory'), 'results'), 0), 'rows'));
        $nativeRows = array_column(array_column(array_column(array_column($observations, 'native'), 'results'), 0), 'rows');
        self::assertCount(2, $nativeRows);
        self::assertNotContains([], $nativeRows);
    }

    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerLibraryReleases(): iterable
    {
        yield '5.6.51' => [GrammarRelease::MySql5651];
        yield '5.7.44' => [GrammarRelease::MySql5744];
        yield '8.0.44' => [GrammarRelease::MySql8044];
    }

    #[DataProvider('providerLibraryReleases')]
    public function testCompareAbsentLibrariesPreservesTheOriginalOsErrno(GrammarRelease $release): void
    {
        $node = new CreateLoadableFunction(new Name('f'), LoadableResult::Decimal, new Text('あ'));
        $comparison = new Comparison();
        $profile = new LanguageProfile($release);
        $observations = [$comparison->compare($node, $profile), $comparison->compare($node, $profile)];

        self::assertSame([true, true], array_column($observations, 'matches'));
        self::assertSame(array_fill(0, 2, [\Fuzz\Target\LibraryErrors::CONTRACT]), array_column($observations, 'contracts'));
        self::assertSame(array_fill(0, 2, 1126), array_column(array_column(array_column($observations, 'native'), 'error'), 0));
        self::assertStringNotContainsString('{' . \Fuzz\Target\LibraryErrors::CONTRACT . '}', serialize(array_column($observations, 'native')));
    }
}
