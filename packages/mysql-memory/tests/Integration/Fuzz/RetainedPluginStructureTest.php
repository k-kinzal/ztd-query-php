<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RetainedPluginStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Account\AlterUser, list<M\Statement|null>}>
     */
    public static function providerAccounts(): iterable
    {
        $user = new M\Name\AccountName(new Name('u'));
        $identification = new M\Account\User\Identification(new Name('sha256_password'), M\Account\User\Credential::Password, new M\Literal\Text('next'));
        $statement = new M\Account\AlterUser(false, [new M\Account\User\UserSpecification($user, $identification, retainCurrent: true)]);
        $specification = static fn (?string $plugin, string $password): M\Account\User\UserSpecification => new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')), new M\Account\User\Identification($plugin === null ? null : new Name($plugin), M\Account\User\Credential::Password, new M\Literal\Text($password)));
        $steps = [null, new M\Account\CreateUser(false, [$specification(null, 'first')]), new M\Account\AlterUser(false, [$specification('sha256_password', 'same')]), new M\Account\AlterUser(false, [$specification(null, '')]), new M\Account\AlterUser(false, [$specification('caching_sha2_password', 'first')]), new M\Account\DropUser(false, [new M\Name\AccountName(new Name('u'))])];
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statement, $steps];
        }
    }

    /**
     * @param list<M\Statement|null> $steps Typed account histories without original input text
     */
    #[DataProvider('providerAccounts')]
    public function testTheSameRetentionRequestDependsOnTheExistingAccountAndPlugin(string $release, M\Account\AlterUser $statement, array $steps): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('SET NAMES utf8mb4');
                $session = (new Instance($release))->connect();
                $session->query('SET NAMES utf8mb4');
                $history = array_map(static function (?M\Statement $setup) use ($native, $session, $statement): array {
                    $session->text = 'unrelated invalid input';
                    $before = $setup === null ? null : (new Native())->observe($native, (new Reference())->sql($setup, $session->semantics()->profile()));
                    $actualBefore = $setup === null ? null : (new Memory())->observe($session, $setup);
                    $expected = (new Native())->observe($native, (new Reference())->sql($statement, $session->semantics()->profile()));
                    return [serialize($before), serialize($actualBefore), serialize($expected), serialize((new Memory())->observe($session, $statement)), $expected['error'][0] ?? null];
                }, $steps);
                self::assertSame(array_column($history, 0), array_column($history, 1));
                self::assertSame(array_column($history, 2), array_column($history, 3));
                self::assertSame([1396, 3894, null, 3878, 3894, 1396], array_column($history, 4));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
