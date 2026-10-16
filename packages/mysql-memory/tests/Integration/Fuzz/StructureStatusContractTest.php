<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use Fuzz\Structure\Audit\Status\Listing;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

#[CoversNothing]
#[Large]
final class StructureStatusContractTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, ShowStatus}>
     */
    public static function providerStructures(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach ([null, VariableScope::Session, VariableScope::Global] as $scope) {
                yield $release->value . ':' . ($scope->value ?? 'default') => [$release, new ShowStatus($scope)];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testSameStructureMatchesIndependentNativeStatusHistories(GrammarRelease $release, ShowStatus $node): void
    {
        $profile = new LanguageProfile($release);
        $comparison = new Comparison();
        $first = $comparison->compare($node, $profile);
        $second = $comparison->compare($node, $profile);

        self::assertTrue($first['matches'], serialize($first));
        self::assertTrue($second['matches'], serialize($second));
        self::assertSame([[Listing::CONTRACT], [Listing::CONTRACT]], [$first['contracts'], $second['contracts']]);
        self::assertNotSame($first['native'], $first['memory']);
        self::assertNotSame($second['native'], $second['memory']);
    }
}
