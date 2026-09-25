<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\MergeStatement;
use SqlSemantics\Model\Write\Decision\MergeDelete;
use SqlSemantics\Model\Write\Decision\MergeNothing;
use SqlSemantics\Model\Write\Decision\MergeRowInsertion;
use SqlSemantics\Model\Write\Decision\MergeUpdate;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Merges;

#[CoversClass(Merges::class)]
#[Medium]
final class MergesTest extends TestCase
{
    public function testWriteKeepsActionsInOrderWithTheirMatchConditions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)'));
        $statement = $binder->bind('MERGE INTO t USING s ON t.id = s.id WHEN MATCHED AND s.n > 0 THEN UPDATE SET n = s.n WHEN MATCHED THEN DELETE WHEN NOT MATCHED BY SOURCE THEN DO NOTHING WHEN NOT MATCHED THEN INSERT (id, n) VALUES (s.id, s.n) RETURNING t.id');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $expected = 'MERGE INTO "public"."t" USING "public"."s" ON ("t"."id" = "s"."id") WHEN MATCHED AND ("s"."n" > 0) THEN UPDATE SET "n" = "s"."n" WHEN MATCHED THEN DELETE WHEN NOT MATCHED BY SOURCE THEN DO NOTHING WHEN NOT MATCHED THEN INSERT("id", "n") VALUES ("s"."id", "s"."n") RETURNING "t"."id" AS "id"';
        self::assertSame($expected, Merges::write($statement)->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(MergeStatement::class, $rebound);
        self::assertSame([MergeUpdate::class, MergeDelete::class, MergeNothing::class, MergeRowInsertion::class], array_map(static fn ($action): string => $action::class, $rebound->merge->actions));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testWriteIncludesTheWithClauseAndDefaultInsertions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)'));
        $statement = $binder->bind('WITH c AS (SELECT 1) MERGE INTO t USING s ON t.id = s.id WHEN NOT MATCHED THEN INSERT DEFAULT VALUES WHEN NOT MATCHED THEN INSERT VALUES (1, 2)');
        self::assertInstanceOf(MergeStatement::class, $statement);
        $expected = 'WITH "c" AS (SELECT 1) MERGE INTO "public"."t" USING "public"."s" ON ("t"."id" = "s"."id") WHEN NOT MATCHED THEN INSERT DEFAULT VALUES WHEN NOT MATCHED THEN INSERT VALUES (1, 2)';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testActionWritesTheConditionBeforeTheEffect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)', 'CREATE TABLE s(id INT, n INT)')))->bind('MERGE INTO t USING s ON t.id = s.id WHEN MATCHED AND s.n > 0 THEN UPDATE SET n = s.n WHEN NOT MATCHED THEN INSERT (id) VALUES (1)');
        self::assertInstanceOf(MergeStatement::class, $statement);
        self::assertSame('WHEN MATCHED AND ("s"."n" > 0) THEN UPDATE SET "n" = "s"."n"', Merges::action($statement->merge->actions[0])->toString());
        self::assertSame('WHEN NOT MATCHED THEN INSERT("id") VALUES (1)', Merges::action($statement->merge->actions[1])->toString());
    }
}
