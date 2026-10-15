<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class AccountNameDomainTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<DropUser>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $users = $release === '5.6.51' ? 16 : 32;
            $hosts = str_starts_with($release, '5.') ? 60 : 255;
            $nodes = [];
            foreach ([['u', null], [str_repeat('u', $users), null], [str_repeat('猫', $users), null], ['u', str_repeat('h', $hosts)], ['u', str_repeat('猫', intdiv($hosts, 3))], ['u', 'example.test'], [' padded ', null], ["u\0tail", null], ['n', "h\0tail"]] as [$user, $host]) {
                $nodes[] = new DropUser(false, [new AccountName(new Name($user), $host === null ? null : new Name($host))]);
            }
            yield $release => [$release, $nodes];
        }
    }

    /**
     * @param list<DropUser> $nodes The unchanged account structures before and after account creation
     */
    #[DataProvider('providerStructures')]
    public function testSameAccountObjectsTrackAccountExistence(string $release, array $nodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session->run('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $profile = $session->semantics()->profile();
                $results = array_map(static function (DropUser $node) use ($native, $session, $profile): array {
                    $sql = (new Reference())->sql($node, $profile);
                    $stages = [[], ['CREATE USER ' . substr($sql, strlen('DROP USER '))], []];
                    return array_map(static function (array $setup) use ($native, $session, $node, $sql): array {
                        array_map(static fn (string $statement): bool|mysqli_result => $native->query($statement), $setup);
                        array_map($session->run(...), $setup);
                        $expected = (new Native())->observe($native, $sql);
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), isset($expected['error'])];
                    }, $stages);
                }, $nodes);

                self::assertSame(array_map(static fn (array $stages): array => array_column($stages, 0), $results), array_map(static fn (array $stages): array => array_column($stages, 1), $results));
                self::assertSame(array_fill(0, count($nodes), [true, false, true]), array_map(static fn (array $stages): array => array_column($stages, 2), $results));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
