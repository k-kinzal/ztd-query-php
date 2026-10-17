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
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class FunctionalIndexStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, CreateIndex}>
     */
    public static function providerIndexes(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['1', '19', '257'] as $offset) {
                $expression = new Arithmetic(ArithmeticOperator::Plus, new ColumnUse(new Name('a')), new NumberLiteral($offset));
                yield $release . ':' . $offset => [$release, new CreateIndex(new Name('k'), new QualifiedName(new Name('t')), [new ExpressionPart($expression)], IndexKind::Unique)];
            }
        }
    }

    #[DataProvider('providerIndexes')]
    public function testSameStructureChangesWithTableAndRowState(string $release, CreateIndex $index): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
                $sql = (new Reference())->sql($index, $session->semantics()->profile());
                $fixtures = [[], ['CREATE TABLE t(id INT PRIMARY KEY)'], ['ALTER TABLE t ADD COLUMN a INT', 'INSERT INTO t VALUES(1,7),(2,7)'], ['DELETE FROM t WHERE id=2'], []];
                $observed = array_map(static function (array $fixture) use ($native, $session, $index, $sql): array {
                    array_map(static fn (string $text): bool|mysqli_result => $native->query($text), $fixture);
                    array_map($session->query(...), $fixture);
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $index);

                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null];
                }, $fixtures);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame([1146, 1054, 1062, null, 1061], array_column($observed, 2));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
