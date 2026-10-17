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
final class PrivilegeNoticeStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerReleases(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release];
        }
    }

    #[DataProvider('providerReleases')]
    public function testSamePrivilegeStructuresPreserveWarningsAcrossObjectAndAccountStates(string $release): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $name = new M\Name\AccountName(new Name('u'), new Name('host'));
            $users = [new M\Account\User\UserSpecification($name)];
            $privileges = [new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Select)];
            $level = new M\Account\Privilege\Level\ObjectLevel(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t'), new Name('d')));
            $grant = new M\Account\Privilege\GrantPrivileges($privileges, M\Account\Privilege\Level\ObjectKind::Table, $level, $users);
            $revoke = new M\Account\Privilege\RevokePrivileges(false, $privileges, M\Account\Privilege\Level\ObjectKind::Table, $level, $users);
            $observed = array_map(static function (string $setup) use ($native, $session, $grant, $revoke): array {
                $native->query($setup);
                $session->query($setup);
                return array_map(static function (M\Statement $node) use ($native, $session): array {
                    $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual)];
                }, [$grant, $revoke]);
            }, ['CREATE DATABASE d', 'CREATE USER u@host', 'CREATE TABLE d.t(a INT)', 'INSERT INTO d.t VALUES(1),(2)', 'DROP TABLE d.t', 'DROP USER u@host']);
            $observed = array_merge(...$observed);

            self::assertSame(array_column($observed, 0), array_column($observed, 1));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
