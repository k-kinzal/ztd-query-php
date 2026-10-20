<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Audit\SessionSetup;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GrantTargetStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>, list<?int>}>
     */
    public static function providerHistories(): iterable
    {
        $database = new Name('absent');
        $table = new QualifiedName(new Name('t'), $database);
        $column = new Name('c');
        $grants = array_map(static fn (M\Account\Privilege\Item\PrivilegeKind $kind): Statement => new M\Account\Privilege\GrantPrivileges([new M\Account\Privilege\Item\StaticPrivilege($kind, [$column])], M\Account\Privilege\Level\ObjectKind::Table, new M\Account\Privilege\Level\ObjectLevel($table), [new M\Account\User\Grantee(new M\Name\CurrentUser())]), [M\Account\Privilege\Item\PrivilegeKind::Select, M\Account\Privilege\Item\PrivilegeKind::Insert, M\Account\Privilege\Item\PrivilegeKind::Update, M\Account\Privilege\Item\PrivilegeKind::References]);
        $create = new M\Table\CreateTable($table, [new M\Table\Column\ColumnDefinition(new M\Name\ColumnName($column), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)))]);
        $history = [...$grants, new M\Server\Database\CreateDatabase(false, $database), ...$grants, $create, ...$grants];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $missing = in_array($release, ['5.6.51', '5.7.44'], true) ? 1146 : 1049;
            yield $release => [$release, $history, [$missing, $missing, $missing, $missing, null, 1146, 1146, 1146, 1146, null, null, null, null, null]];
        }
    }

    /**
     * @param list<Statement> $history The same grants before database creation, before table creation and after both
     * @param list<?int> $errors The expected catalog transitions
     */
    #[DataProvider('providerHistories')]
    public function testColumnGrantsResolveTheSameTargetsAcrossCatalogStates(string $release, array $history, array $errors): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->set_charset('utf8mb4');
                $native->query("SET SESSION sql_mode='', collation_connection='utf8mb4_bin'");
                $session = (new Instance($release))->connect();
                (new SessionSetup())->apply($session, '');
                $session->text = 'unrelated invalid input';
                $observed = array_map(static function (Statement $node) use ($native, $session): array {
                    $sql = (new Reference())->sql($node, $session->semantics()->profile());
                    $expected = (new Native())->observe($native, $sql);
                    $actual = (new Memory())->observe($session, $node);
                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $history);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame($errors, array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
