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
use SqlSemantics\Model\Write\Conflict\DoNothing;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AnyConflict::class)]
#[Medium]
final class AnyConflictTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING', 'INSERT INTO "public"."t" VALUES (1) ON CONFLICT DO NOTHING'])]
    #[TestWith([Dialect::Sqlite, 'INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING', 'INSERT INTO "main"."t" VALUES (1) ON CONFLICT DO NOTHING'])]
    #[TestWith([Dialect::MySql, 'INSERT INTO t VALUES(1) ON DUPLICATE KEY UPDATE id=2', 'INSERT INTO `t` VALUES (1) ON DUPLICATE KEY UPDATE `id` = 2'])]
    public function testSelectsEveryConflictWhenNoIndexOrConstraintIsNamed(Dialect $dialect, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertInstanceOf(AnyConflict::class, $statement->conflicts[0]->target);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(InsertStatement::class, $rebound);
        self::assertInstanceOf(AnyConflict::class, $rebound->conflicts[0]->target);
    }

    public function testCarriesNoOperands(): void
    {
        self::assertSame([], get_object_vars(new AnyConflict()));
    }

    public function testIsRetainedAsTheTargetOfAConstructedAction(): void
    {
        $target = new AnyConflict();
        $action = new DoNothing($target, new Node('conflict', 0, []));
        self::assertSame($target, $action->target);
        self::assertSame(ActionKind::Nothing, $action->action);
    }
}
