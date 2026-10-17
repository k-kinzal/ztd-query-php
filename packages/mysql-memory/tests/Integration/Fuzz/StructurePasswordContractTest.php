<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Structure\Audit\Comparison;
use Fuzz\Target\Password\Retention;
use Fuzz\Target\RandomPasswords;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;

#[CoversNothing]
#[Large]
final class StructurePasswordContractTest extends TestCase
{
    /**
     * @return iterable<string, array{GrammarRelease, SetPassword, list<string>}>
     */
    public static function providerStructures(): iterable
    {
        foreach ([GrammarRelease::MySql8044, GrammarRelease::MySql847, GrammarRelease::MySql910] as $release) {
            foreach ([false, true] as $retain) {
                foreach ([null, new CurrentUser()] as $index => $user) {
                    yield $release->value . ':' . (int) $retain . ':' . $index => [$release, new SetPassword($user, null, retainCurrent: $retain), $retain ? [RandomPasswords::CONTRACT, Retention::CONTRACT] : [RandomPasswords::CONTRACT]];
                }
            }
        }
    }

    /**
     * @param list<string> $contracts The exact validated relationships required by this request
     */
    #[DataProvider('providerStructures')]
    public function testSameStructureValidatesItsOwnPasswordOnIndependentServers(GrammarRelease $release, SetPassword $node, array $contracts): void
    {
        $profile = new LanguageProfile($release);
        $comparison = new Comparison();
        $first = $comparison->compare($node, $profile);
        $second = $comparison->compare($node, $profile);

        self::assertTrue($first['matches'], serialize($first));
        self::assertTrue($second['matches'], serialize($second));
        self::assertSame([$contracts, $contracts], [$first['contracts'], $second['contracts']]);
        self::assertStringNotContainsString('{' . RandomPasswords::CONTRACT . '}', serialize([$first['native'], $first['memory'], $second['native'], $second['memory']]));
    }
}
