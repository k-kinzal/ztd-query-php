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
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class RenameUserStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<list<Statement>>}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $histories = [];
            $index = 0;
            foreach (['%', '猫', '猫abcdef'] as $from) {
                foreach (['%', '犬', '犬abcdef'] as $to) {
                    $a = new AccountName(new Name('a' . $index), new Name($from));
                    $b = new AccountName(new Name('b' . $index++), new Name($to));
                    $create = new CreateUser(false, [new UserSpecification($a)]);
                    $rename = new RenameUser([new UserRenaming($a, $b)]);
                    $reverse = new RenameUser([new UserRenaming($b, $a)]);
                    $drop = new DropUser(false, [$a, $b]);
                    $histories[] = [$rename, $create, $rename, $reverse, $rename, $rename, $reverse, $drop];
                }
            }
            yield $release => [$release, $histories];
        }
    }

    /**
     * @param list<list<Statement>> $histories Accepted constructor values reused before and after account creation, rename, failure and removal
     */
    #[DataProvider('providerHistories')]
    public function testSameNamesExecuteAcrossAccountStateTransitions(string $release, array $histories): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new Instance($release))->connect();
            $native->query('SET NAMES utf8mb4');
            $session->query('SET NAMES utf8mb4');
            $differences = array_map(static function (array $history) use ($native, $session): array {
                return array_filter(array_map(static function (Statement $node) use ($native, $session): ?array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $node);
                    return serialize($expected) === serialize($actual) ? null : [$sql, $expected, $actual];
                }, $history));
            }, $histories);

            self::assertSame([], array_merge(...$differences));
            self::assertSame('unrelated invalid input', $session->text);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
