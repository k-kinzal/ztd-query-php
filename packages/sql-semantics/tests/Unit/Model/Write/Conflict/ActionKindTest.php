<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Conflict;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\InsertStatement;
use SqlSemantics\Model\Write\Conflict\ActionKind;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ActionKind::class)]
#[Medium]
final class ActionKindTest extends TestCase
{
    public function testRepresentsEveryConflictOperation(): void
    {
        self::assertSame(['nothing', 'update', 'replace'], array_column(ActionKind::cases(), 'value'));
    }

    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING', ActionKind::Nothing])]
    #[TestWith([Dialect::PostgreSql, 'INSERT INTO t VALUES(1) ON CONFLICT(id) DO UPDATE SET id=2', ActionKind::Update])]
    #[TestWith([Dialect::Sqlite, 'INSERT INTO t VALUES(1) ON CONFLICT(id) DO NOTHING', ActionKind::Nothing])]
    #[TestWith([Dialect::MySql, 'INSERT INTO t VALUES(1) ON DUPLICATE KEY UPDATE id=2', ActionKind::Update])]
    public function testDerivesTheOperationFromTheConflictClause(Dialect $dialect, string $sql, ActionKind $action): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build('CREATE TABLE t(id INTEGER)')))->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertCount(1, $statement->conflicts);
        self::assertSame($action, $statement->conflicts[0]->action);
    }
}
