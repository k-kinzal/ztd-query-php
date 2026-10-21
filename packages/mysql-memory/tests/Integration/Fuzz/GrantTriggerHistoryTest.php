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
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversNothing]
#[Large]
final class GrantTriggerHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Statement}>
     */
    public static function providerChanges(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            $user = new M\Name\AccountName(new Name('ABCDE'));
            $current = new M\Name\CurrentUser();
            yield $release . ':grants' => [$release, new M\Utility\Show\Server\ShowGrants($user)];
            yield $release . ':proxy' => [$release, new M\Account\Privilege\RevokeProxy(false, $current, [new M\Account\User\UserSpecification(new M\Name\CurrentUser())])];
            yield $release . ':trigger' => [$release, new M\Utility\Show\Program\ShowCreateTrigger(new QualifiedName(new Name('2')))];
        }
    }

    #[DataProvider('providerChanges')]
    public function testTheUnchangedRequestSucceedsAfterItsCatalogObjectsExist(string $release, M\Statement $node): void
    {
        $observation = (new Comparison())->compare($node, new LanguageProfile(GrammarRelease::from('mysql-' . $release)));
        self::assertTrue($observation['matches'], serialize($observation));
        self::assertNotEmpty($observation['transitions']);
        $last = $observation['transitions'][count($observation['transitions']) - 1];
        self::assertSame($observation['sql'], $last['sql']);
        self::assertArrayNotHasKey('error', $last['native']);
        self::assertNotEmpty($last['native']['results']);
    }
}
