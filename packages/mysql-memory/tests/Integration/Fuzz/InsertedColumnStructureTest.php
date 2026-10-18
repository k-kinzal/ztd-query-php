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
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class InsertedColumnStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<M\Dml\Insert\InsertRows>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new \Fuzz\Structure\Choices('inserted-columns-20261018');
        $statements = array_map(static function (int $trial) use ($choices): M\Dml\Insert\InsertRows {
            $head = new M\Dml\Insert\InsertInto(false, null, false, new M\Dml\WriteTarget(new QualifiedName(new Name('t'))));
            $one = $choices->one([new M\Name\ColumnUse(new Name('a')), new M\Literal\NumberLiteral((string) $trial)]);
            $two = new M\Name\ColumnUse(new Name($choices->one(['a', 'b'])));
            return new M\Dml\Insert\InsertRows($head, [new M\Dml\Insert\InsertedRow([$one, $two])]);
        }, range(0, 9));
        foreach (['5.7.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $statements];
        }
    }

    /**
     * @param list<M\Dml\Insert\InsertRows> $statements Identical objects across missing, populated, altered and removed tables
     */
    #[DataProvider('providerStructures')]
    public function testTheSameWritesObserveColumnDefaultsAndEarlierAssignments(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $native->query('SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $session = (new Instance($release))->connect('root', 'localhost');
                $session->run('CREATE DATABASE d; USE d; SET NAMES utf8mb4 COLLATE utf8mb4_general_ci');
                $select = new M\QueryRoot(new M\Query\Select([], [new M\Query\Star()], new M\Relation\TableReference(new QualifiedName(new Name('t')))));
                $observed = array_map(static function (string $setup) use ($native, $session, $statements, $select): array {
                    $native->query($setup);
                    $session->run($setup);
                    return array_map(static function (M\Dml\Insert\InsertRows|M\QueryRoot $node) use ($native, $session): array {
                        $expected = (new Native())->observe($native, (new Reference())->sql($node, $session->semantics()->profile()));
                        $session->text = 'unrelated invalid input';
                        $actual = (new Memory())->observe($session, $node);
                        return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                    }, [...$statements, $select]);
                }, ['DO 0', 'CREATE TABLE t(a INT DEFAULT 4,b INT DEFAULT 7)', 'ALTER TABLE t ALTER a SET DEFAULT 11', 'DROP TABLE t']);
                $trials = array_merge(...$observed);
                self::assertSame(array_column($trials, 0), array_column($trials, 1));
                self::assertSame([1146, null, null, 1146], array_map(static fn (array $state): ?int => $state[0][2], $observed));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
