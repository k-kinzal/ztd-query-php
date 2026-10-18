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
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Dml\ImportTable;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CachedTable;
use SqlSemantics\Platform\MySql\Statement\Server\KeyCache\CacheIndex;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\InspectedTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class DatabaseStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>, list<?int>, list<?int>}>
     */
    public static function providerStructures(): iterable
    {
        $user = new UserSpecification(new AccountName(new Name('u')));
        $routine = new ObjectLevel(new QualifiedName(new Name('p')));
        $execute = [new StaticPrivilege(PrivilegeKind::Execute)];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $legacy = str_starts_with($release, '5.');
            $nodes = [
                new ShowCreateView(new InspectedTable(new QualifiedName(new Name('v')))),
                new ShowCreateView(new InspectedTable(new QualifiedName(new Name('v'), new Name('d')))),
                new CacheIndex([new CachedTable(new QualifiedName(new Name('t')))], null),
                new CacheIndex([new CachedTable(new QualifiedName(new Name('t')))], new Name('missing')),
                new GrantPrivileges($execute, ObjectKind::Procedure, $routine, [$user]),
                new RevokePrivileges(false, $execute, ObjectKind::Procedure, $routine, [$user]),
                new GrantPrivileges([new AllPrivileges()], ObjectKind::Table, new CurrentDatabaseLevel(), [$user]),
                new \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure(new QualifiedName(new Name('revoker')), new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredRevokePrivileges(false, [new AllPrivileges()], ObjectKind::Procedure, new CurrentDatabaseLevel(), [$user])),
            ];
            $before = [1046, $legacy ? 1146 : 1049, 1046, 1046, 1046, 1046, 1046, 1046];
            $after = [null, null, null, 1284, null, null, null, null];
            if (!$legacy) {
                $nodes[] = new ImportTable([new Text('x')]);
                $nodes[] = new ImportTable([new Text('/var/lib/mysql-files/x.sdi')]);
                $before = [...$before, 1046, 1046];
                $after = [...$after, 1290, 3608];
            }
            yield $release => [$release, $nodes, $before, $after];
        }
    }

    /**
     * @param list<Statement> $nodes The original structures reused after fixture creation
     * @param list<?int> $beforeErrors Expected database constraints
     * @param list<?int> $afterErrors Remaining independent constraints, or successful completion
     */
    #[DataProvider('providerStructures')]
    public function testDirectStructuresResolveTheirDatabaseBeforeLaterConstraints(string $release, array $nodes, array $beforeErrors, array $afterErrors): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $sql = array_map(static fn (Statement $node): string => (new Reference())->sql($node, $session->semantics()->profile()), $nodes);
                $observe = static function (Statement $node) use ($session): array {
                    $result = (new Memory())->observe($session, $node);

                    return [serialize($result), $result['error'][0] ?? null];
                };
                $session->text = 'unrelated invalid input';
                $before = array_map($observe, $nodes);
                self::assertSame(array_map(static fn (string $text): string => serialize((new Native())->observe($native, $text)), $sql), array_column($before, 0));
                self::assertSame($beforeErrors, array_column($before, 1));
                $fixture = ['CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', 'USE d', 'CREATE TABLE t(a INT)', 'CREATE VIEW v AS SELECT a FROM t', 'CREATE PROCEDURE p() SELECT 1', 'CREATE USER u'];
                array_map(static fn (string $text): bool|mysqli_result => $native->query($text), $fixture);
                array_map($session->run(...), $fixture);
                $session->text = 'unrelated invalid input';
                $after = array_map($observe, $nodes);

                self::assertSame(array_map(static fn (string $text): string => serialize((new Native())->observe($native, $text)), $sql), array_column($after, 0));
                self::assertSame($afterErrors, array_column($after, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
