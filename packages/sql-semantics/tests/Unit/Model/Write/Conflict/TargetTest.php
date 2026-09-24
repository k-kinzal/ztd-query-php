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
use SqlSemantics\Model\Write\Conflict\AnyConflict;
use SqlSemantics\Model\Write\Conflict\ConstraintConflict;
use SqlSemantics\Model\Write\Conflict\IndexConflict;
use SqlSemantics\Model\Write\Conflict\Target;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Target::class)]
#[Medium]
final class TargetTest extends TestCase
{
    #[TestWith(['INSERT INTO t VALUES(1) ON CONFLICT DO NOTHING', AnyConflict::class])]
    #[TestWith(['INSERT INTO t VALUES(1) ON CONFLICT(id) WHERE id>0 DO NOTHING', IndexConflict::class])]
    #[TestWith(['INSERT INTO t VALUES(1) ON CONFLICT ON CONSTRAINT t_key DO NOTHING', ConstraintConflict::class])]
    public function testSelectsAConflictByNothingAnIndexOrAConstraint(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind($sql);
        self::assertInstanceOf(InsertStatement::class, $statement);
        self::assertSame($class, $statement->conflicts[0]->target::class);
    }

    public function testIndexAndConstraintSelectorsKeepTheirOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $index = $binder->bind('INSERT INTO t VALUES(1,2) ON CONFLICT(id, n) WHERE id>0 DO NOTHING');
        self::assertInstanceOf(InsertStatement::class, $index);
        $target = $index->conflicts[0]->target;
        self::assertInstanceOf(IndexConflict::class, $target);
        self::assertCount(2, $target->keys);
        self::assertSame('>', $target->predicate?->spelling());
        $constraint = $binder->bind('INSERT INTO t VALUES(1,2) ON CONFLICT ON CONSTRAINT t_key DO NOTHING');
        self::assertInstanceOf(InsertStatement::class, $constraint);
        $named = $constraint->conflicts[0]->target;
        self::assertInstanceOf(ConstraintConflict::class, $named);
        self::assertSame('t_key', $named->name);
    }
}
