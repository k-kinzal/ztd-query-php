<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Call\Clock;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;

#[CoversNothing]
#[Large]
final class StructureClockTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease}>
     */
    public static function providerReleases(): iterable
    {
        foreach ([GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            yield $release->value => [$release];
        }
    }

    /**
     * @throws JsonException
     */
    #[DataProvider('providerReleases')]
    public function testCompareUsesTheExplicitSessionInstantForBothObservers(GrammarRelease $release): void
    {
        $node = new ClockCall(Clock::UtcTime);
        $observation = (new Comparison())->compare($node, new LanguageProfile($release));

        self::assertTrue($observation['matches'], json_encode($observation, JSON_THROW_ON_ERROR));
        $results = $observation['native']['results'];
        self::assertIsArray($results);
        self::assertIsArray($results[0]);
        self::assertSame([['22:13:20']], $results[0]['rows']);
    }
}
