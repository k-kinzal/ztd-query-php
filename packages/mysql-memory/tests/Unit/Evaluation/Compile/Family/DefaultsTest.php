<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Family;

use MySqlMemory\Evaluation\Compile\Family\Defaults;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Defaults::class)]
#[Small]
final class DefaultsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<list<string|null>>}>
     */
    public static function providerInputs(): iterable
    {
        yield 'merged base' => ['SELECT DEFAULT(x) FROM (SELECT a AS x FROM t) d', [['7']]];
        yield 'materialized base' => ['SELECT DEFAULT(x) FROM (SELECT a AS x FROM t LIMIT 1) d', [['7']]];
        yield 'empty alias' => ['SELECT DEFAULT(``) FROM (SELECT a AS `` FROM t) d', [['7']]];
        yield 'computed buffer' => ['SELECT DEFAULT(x) FROM (SELECT a+0 AS x FROM t LIMIT 1) d', [[null]]];
        yield 'constant buffer' => ['SELECT DEFAULT(x) FROM (SELECT 1 AS x) d', [['0']]];
        yield 'view' => ['SELECT DEFAULT(x) FROM v', [['7']]];
    }

    /**
     * @param list<list<string|null>> $rows
     */
    #[DataProvider('providerInputs')]
    public function testColumnFollowsResolvedDeclarationsAndTemporarySlots(string $sql, array $rows): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT DEFAULT 7); INSERT INTO t VALUES (3); CREATE VIEW v AS SELECT a AS x FROM t');
        $result = $session->query($sql)[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame($rows, $result->rows);
    }

    public function testNameKeepsTheLegacyMaterializedAliasInTheMissingDefaultCondition(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT NOT NULL)');
        $this->expectExceptionMessage("Field 'x' doesn't have a default value");
        $session->query('SELECT DEFAULT(x) FROM (SELECT a AS x FROM t) d');
    }
}
