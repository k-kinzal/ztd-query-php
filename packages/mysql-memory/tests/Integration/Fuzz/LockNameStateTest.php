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
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LockNameStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>, list<list<int|null>>}>
     */
    public static function providerStructures(): iterable
    {
        $qualified = new QualifiedName(new Name('t'), new Name('lock_domain'));
        $unqualified = new QualifiedName(new Name('t'));
        $statements = [
            new LockTables([new TableLock($unqualified, new Name('a'), LockMode::ReadLocal), new TableLock($qualified, new Name('b'), LockMode::LowPriorityWrite)]),
            new LockTables([new TableLock($qualified, new Name('a'), LockMode::LowPriorityWrite), new TableLock($unqualified, new Name('b'), LockMode::ReadLocal)]),
            new LockTables([new TableLock($unqualified, new Name('a'), LockMode::LowPriorityWrite), new TableLock($qualified, new Name('b'), LockMode::LowPriorityWrite)]),
            new LockTables([new TableLock($qualified, new Name('a'), LockMode::LowPriorityWrite), new TableLock($unqualified, new Name('b'), LockMode::LowPriorityWrite)]),
            new LockTables([new TableLock($unqualified, null, LockMode::Read)]),
            new LockTables([new TableLock($qualified, null, LockMode::LowPriorityWrite)]),
        ];
        foreach (['5.6.51', '5.7.44'] as $release) {
            yield $release => [$release, $statements, [[1046, 1046, 1046, 1046, 1046, 1146], [1146, 1146, 1146, 1146, 1146, 1146], [null, null, null, null, null, null]]];
        }
    }

    /**
     * @param list<Statement> $statements The same requests before database selection, after selection and after creating the table
     * @param list<list<int|null>> $expectedCodes Every request's native error through the history
     */
    #[DataProvider('providerStructures')]
    public function testSameObjectsPreserveReachedWarningsAcrossDatabaseAndTableCreation(string $release, array $statements, array $expectedCodes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE lock_domain');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE lock_domain; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $observed = array_map(static function (string $setup) use ($native, $session, $statements): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (Statement $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        $native->query('UNLOCK TABLES');
                        (new Memory())->observe($session, new \SqlSemantics\Platform\MySql\Statement\Server\Lock\UnlockTables());
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, $statements);
                }, ['DO 0', 'USE lock_domain', 'CREATE TABLE t(a INT)']);
                $trials = array_merge(...$observed);

                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame($expectedCodes, array_map(static fn (array $state): array => array_column($state, 2), $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
