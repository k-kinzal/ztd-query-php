<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Conflict\AnyConflict;
use SqlSemantics\Model\Write\Conflict\ReplaceRow;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Write\Conflicts;

#[CoversClass(Conflicts::class)]
#[Medium]
final class ConflictsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t (id) VALUES (1) ON CONFLICT (id) WHERE id > 0 DO UPDATE SET n = excluded.n WHERE t.n < 1', 'ON CONFLICT("id") WHERE ("id" > 0) DO UPDATE SET "n" = "excluded"."n" WHERE ("t"."n" < 1)'])]
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t (id) VALUES (1) ON CONFLICT ON CONSTRAINT t_key DO NOTHING', 'ON CONFLICT ON CONSTRAINT "t_key" DO NOTHING'])]
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t (id) VALUES (1) ON CONFLICT DO NOTHING', 'ON CONFLICT DO NOTHING'])]
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t (id) VALUES (1) ON CONFLICT (id, n) DO UPDATE SET (id, n) = (SELECT 1, 2)', 'ON CONFLICT("id", "n") DO UPDATE SET("id", "n") = (SELECT 1, 2)'])]
    #[TestWith([Dialect::Sqlite, 'INSERT INTO t (id) VALUES (1) ON CONFLICT (id) WHERE id > 0 DO UPDATE SET n = 1 WHERE n < 1', 'ON CONFLICT("id") WHERE ("id" > 0) DO UPDATE SET "n" = 1 WHERE ("n" < 1)'])]
    #[TestWith([Dialect::MySql, 'INSERT INTO t (id) VALUES (1) ON DUPLICATE KEY UPDATE n = 2', 'ON DUPLICATE KEY UPDATE `n` = 2'])]
    public function testWriteKeepsTheTargetDistinctFromTheAction(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertSame($expected, Conflicts::write($statement->conflicts[0], $dialect)->toString());
        self::assertStringEndsWith(' ' . $expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWriteRejectsAReplaceActionInAnOnConflictClause(): void
    {
        $this->expectException(InvalidStructure::class);
        Conflicts::write(new ReplaceRow(new AnyConflict(), new Node('conflict', 0, [])), Dialect::PostgreSql);
    }
}
