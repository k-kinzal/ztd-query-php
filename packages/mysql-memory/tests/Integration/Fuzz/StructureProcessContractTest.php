<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use Fuzz\Structure\Audit\Process\Listing;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist;

#[CoversNothing]
#[Large]
final class StructureProcessContractTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, ShowProcesslist}>
     */
    public static function providerStructures(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach ([false, true] as $full) {
                yield $release->value . ':' . (int) $full => [$release, new ShowProcesslist($full)];
            }
        }
    }

    #[DataProvider('providerStructures')]
    public function testSameStructureMatchesIndependentNativeProcessLists(GrammarRelease $release, ShowProcesslist $node): void
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
