<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Conflict;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\Conflict\ActionKind;
use SqlSemantics\Model\Write\Conflict\AnyConflict;
use SqlSemantics\Model\Write\Conflict\ConstraintConflict;
use SqlSemantics\Model\Write\Conflict\DoNothing;
use SqlSemantics\Model\Write\Conflict\IndexConflict;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DoNothing::class)]
#[Medium]
final class DoNothingTest extends TestCase
{
    public function testDerivesTheNothingOperation(): void
    {
        $target = new ConstraintConflict('t_key');
        $source = new Node('conflict', 0, []);
        $action = new DoNothing($target, $source);
        self::assertSame(ActionKind::Nothing, $action->action);
        self::assertSame($target, $action->target);
        self::assertSame($source, $action->source);
    }

    public function testCarriesNoAssignmentsOrRowPredicate(): void
    {
        $action = new DoNothing(new AnyConflict(), new Node('conflict', 0, []));
        self::assertFalse(property_exists($action, 'assignments'));
        self::assertFalse(property_exists($action, 'where'));
    }

    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING', AnyConflict::class, 'INSERT INTO "public"."t" VALUES (1) ON CONFLICT DO NOTHING'])]
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t VALUES(1) ON CONFLICT(id) DO NOTHING', IndexConflict::class, 'INSERT INTO "public"."t" VALUES (1) ON CONFLICT("id") DO NOTHING'])]
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t VALUES(1) ON CONFLICT ON CONSTRAINT t_key DO NOTHING', ConstraintConflict::class, 'INSERT INTO "public"."t" VALUES (1) ON CONFLICT ON CONSTRAINT "t_key" DO NOTHING'])]
    #[TestWith([Dialect::Sqlite, 'INSERT INTO t VALUES(1) ON CONFLICT(id) DO NOTHING', IndexConflict::class, 'INSERT INTO "main"."t" VALUES (1) ON CONFLICT("id") DO NOTHING'])]
    public function testBindsFromAnOnConflictDoNothingClause(Dialect $dialect, string $sql, string $target, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf(DoNothing::class, $statement->conflicts[0]);
        self::assertSame($target, $statement->conflicts[0]->target::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
