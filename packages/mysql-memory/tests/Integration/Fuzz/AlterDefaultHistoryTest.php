<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Choices;
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
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class AlterDefaultHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Alter\AlterTable>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $choices = new Choices('alter-default-domains:' . $release);
            $structures = [];
            for ($trial = 0; $trial < 12; $trial++) {
                $default = new M\Table\Column\DefaultLiteral(new M\Literal\StringLiteral(['2001-01-' . str_pad((string) $choices->integer(1, 28), 2, '0', STR_PAD_LEFT)]));
                if (!str_starts_with($release, '5.') && $trial % 2 === 0) {
                    $default = new M\Table\Column\DefaultExpression(new M\Call\ClockCall(M\Call\Clock::Now, new M\Literal\Numeral((string) $choices->integer(0, 6))));
                }
                $structures[] = new M\Alter\AlterTable(new QualifiedName(new Name('t')), [new M\Alter\Column\DefaultSetting(new M\Name\ColumnName(new Name('a')), $default)]);
            }
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<M\Alter\AlterTable> $structures Literal and clock defaults sampled from their distinct semantic roles
     */
    #[DataProvider('providerStructures')]
    public function testTheSameAlterationUsesOnlyItsStructureAcrossTableAndColumnStates(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->set_charset('utf8mb4');
            $native->query('SET timestamp=1704067200');
            $session = (new Instance($release))->connect();
            $session->query('SET NAMES utf8mb4');
            $session->query('SET timestamp=1704067200');
            array_map(static function (M\Alter\AlterTable $alter) use ($native, $session): void {
                $history = array_map(static function (string $setup) use ($native, $session, $alter): array {
                    array_map(static function (string $sql) use ($native, $session): void {
                        $native->query($sql);
                        $session->query($sql);
                    }, explode('; ', $setup));
                    $expected = (new Native())->observe($native, (new Reference())->sql($alter, $session->semantics()->profile()));
                    $session->text = 'opaque non-SQL label';
                    self::assertSame(serialize($expected), serialize((new Memory())->observe($session, $alter)), $setup);
                    return $expected;
                }, ['CREATE DATABASE IF NOT EXISTS d; USE d; DROP TABLE IF EXISTS t', 'CREATE TABLE t(a DATETIME(6)); INSERT INTO t VALUES(NULL)', 'ALTER TABLE t CHANGE a b DATETIME(6)', 'DROP TABLE t', 'CREATE TABLE t(a DATETIME(6)); INSERT INTO t VALUES(NULL)']);
                self::assertSame([1146, null, 1054, 1146, null], array_map(static fn (array $observation): ?int => $observation['error'][0] ?? null, $history));
                self::assertSame('opaque non-SQL label', $session->text);
                array_map(static function (string $sql) use ($native, $session): void {
                    $node = $session->analyze($sql)->statement;
                    self::assertInstanceOf(M\Statement::class, $node);
                    self::assertSame(serialize((new Native())->observe($native, $sql)), serialize((new Memory())->observe($session, $node)));
                }, ['INSERT INTO t() VALUES()', 'SELECT a FROM t ORDER BY a']);
            }, $structures);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
