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
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class ValueRowsStateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, InsertQuery, list<int|null>}>
     */
    public static function providerSources(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            $defaults = new ValuesQuery([new ValueRow([]), new ValueRow([])]);
            yield $release . ':empty rows' => [$release, new InsertQuery(new InsertInto(false, null, false, new WriteTarget(new QualifiedName(new Name('t')))), $defaults), [1146, null, null, 1364, null]];
            $explicit = new ValuesQuery([new ValueRow([new DefaultRequest(), new NumberLiteral('2')])]);
            yield $release . ':explicit default' => [$release, new InsertQuery(new InsertInto(false, null, false, new WriteTarget(new QualifiedName(new Name('t')))), $explicit), [1146, 1136, null, 1364, null]];
        }
    }

    /**
     * @param list<int|null> $codes
     */
    #[DataProvider('providerSources')]
    public function testSameValuesObjectRebindsColumnCountsAndDefaults(string $release, InsertQuery $statement, array $codes): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
                $sql = (new Reference())->sql($statement, $session->semantics()->profile());
                $fixtures = [[], ['CREATE TABLE t(a INT DEFAULT 7)'], ['ALTER TABLE t ADD COLUMN b INT DEFAULT 8'], ['ALTER TABLE t MODIFY a INT NOT NULL'], ['ALTER TABLE t ALTER a SET DEFAULT 11']];
                $reader = $session->semantics()->analyze('SELECT * FROM t ORDER BY a')->statement;
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $reader);
                $observed = array_map(static function (array $fixture) use ($native, $session, $statement, $sql, $reader): array {
                    array_map(static fn (string $text): bool|mysqli_result => $native->query($text), $fixture);
                    array_map($session->query(...), $fixture);
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'unrelated invalid input';
                    $actual = (new Memory())->observe($session, $statement);

                    $expectedRows = (new Native())->observe($native, 'SELECT * FROM t ORDER BY a');
                    $actualRows = (new Memory())->observe($session, $reader);

                    return [serialize($expected), serialize($actual), $expected['error'][0] ?? null, serialize($expectedRows), serialize($actualRows)];
                }, $fixtures);

                self::assertSame(array_column($observed, 0), array_column($observed, 1));
                self::assertSame($codes, array_column($observed, 2));
                self::assertSame(array_column($observed, 3), array_column($observed, 4));
                self::assertSame('unrelated invalid input', $session->text);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
        }
    }
}
