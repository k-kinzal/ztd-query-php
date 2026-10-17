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
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerClose;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerOpen;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class HandlerStructureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<Statement>, list<?int>}>
     */
    public static function providerStructures(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            $name = new Name('h` WHERE LIMIT');
            $predicate = new Comparison(ComparisonOperator::Greater, new ColumnUse(new Name('a'), new QualifiedName($name)), new NumberLiteral('1'));
            $first = new HandlerScan(new OpenHandler($name), ScanDirection::First, $predicate, new RowLimit(new NumberLiteral('2')));
            $last = new HandlerIndexRead(new OpenHandler($name), new Name('PRIMARY'), IndexDirection::Last, new Comparison(ComparisonOperator::Equal, new ColumnUse(new Name('b')), new StringLiteral([' WHERE LIMIT '])), new RowLimit(new NumberLiteral('2')));
            $seek = new HandlerIndexSeek(new OpenHandler($name), new Name('PRIMARY'), KeyComparison::GreaterOrEqual, [new NumberLiteral('2')], new Comparison(ComparisonOperator::Less, new ColumnUse(new Name('a')), new NumberLiteral('4')), new RowLimit(new NumberLiteral('10')));
            $open = new HandlerOpen(new QualifiedName(new Name('t'), new Name('d')), $name);
            $close = new HandlerClose($name);
            $missing = new HandlerScan(new OpenHandler($name), ScanDirection::First, new ColumnUse(new Name('absent')));
            yield $release => [$release, [$first, $open, $first, $last, $seek, $missing, $close, $first, $open, $first], [1109, null, null, null, null, 1054, null, 1109, null, null]];
        }
    }

    /**
     * @param list<Statement> $nodes Original predicates and reads, reused across handler state transitions
     * @param list<?int> $errors Expected SQL outcomes before, during and after the handler lifetime
     */
    #[DataProvider('providerStructures')]
    public function testSameReadsUseTypedPredicatesAndOpenedTableState(string $release, array $nodes, array $errors): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                $fixture = ['SET NAMES utf8mb4 COLLATE utf8mb4_general_ci', 'CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci', 'USE d', 'CREATE TABLE t(a INT PRIMARY KEY, b VARCHAR(30))', "INSERT INTO t VALUES(1, 'skip'), (2, ' WHERE LIMIT '), (3, ' WHERE LIMIT '), (4, NULL)"];
                array_map(static fn (string $sql): bool|mysqli_result => $native->query($sql), $fixture);
                array_map($session->run(...), $fixture);
                $profile = $session->semantics()->profile();
                $expected = array_map(static fn (Statement $node): string => serialize((new Native())->observe($native, (new Reference())->sql($node, $profile))), $nodes);
                $session->text = 'unrelated invalid input';
                $actual = array_map(static function (Statement $node) use ($session): array {
                    $result = (new Memory())->observe($session, $node);

                    return [serialize($result), $result['error'][0] ?? null];
                }, $nodes);

                self::assertSame($expected, array_column($actual, 0));
                self::assertSame($errors, array_column($actual, 1));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
