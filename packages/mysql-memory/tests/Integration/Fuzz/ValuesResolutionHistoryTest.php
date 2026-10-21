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
final class ValuesResolutionHistoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\QueryRoot>}>
     */
    public static function providerQueries(): iterable
    {
        $value = new M\Query\SelectExpression(new M\Expression\Access\InsertedColumn(new M\Name\ColumnUse(new Name('a'))), new Name('inserted'));
        $other = new M\Query\SelectExpression(new M\Name\ColumnUse(new Name('b')), new Name('other'));
        $table = new M\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t'), new Name('d')));
        $queries = array_map(static fn (array $items): M\QueryRoot => new M\QueryRoot(new M\Query\Select([], $items, $table)), [[$value], [$value, $other], [$other, $value]]);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $queries];
        }
    }

    /**
     * @param list<M\QueryRoot> $queries The same directly constructed operands before and after relation and column changes
     */
    #[DataProvider('providerQueries')]
    public function testResolutionNoticesFollowTheSameStructuresThroughCatalogTransitions(string $release, array $queries): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $native->set_charset('utf8mb4');
            $session = (new Instance($release))->connect();
            $session->query('SET NAMES utf8mb4');
            array_map(static function (string $setup) use ($native, $session, $queries): void {
                $node = $session->analyze($setup)->statement;
                self::assertInstanceOf(M\Statement::class, $node);
                self::assertSame(serialize((new Native())->observe($native, $setup)), serialize((new Memory())->observe($session, $node)), $setup);
                array_map(static function (M\QueryRoot $query) use ($native, $session, $setup): void {
                    $sql = (new Reference())->sql($query, $session->semantics()->profile());
                    $session->text = 'opaque non-SQL label';
                    self::assertSame(serialize((new Native())->observe($native, $sql)), serialize((new Memory())->observe($session, $query)), $setup . ': ' . $sql);
                    self::assertSame('opaque non-SQL label', $session->text);
                }, $queries);
            }, ['CREATE DATABASE d', 'CREATE TABLE d.t(a INT)', 'INSERT INTO d.t VALUES(1)', 'ALTER TABLE d.t ADD COLUMN b INT', 'UPDATE d.t SET a=2,b=3', 'ALTER TABLE d.t DROP COLUMN a', 'DROP TABLE d.t']);
            $native->close();
        } finally {
            $container->stop();
        }
    }
}
