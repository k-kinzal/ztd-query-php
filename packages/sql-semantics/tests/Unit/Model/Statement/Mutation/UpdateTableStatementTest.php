<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Policy\ConstraintResponse;
use SqlSemantics\SchemaBuilder;

#[CoversClass(UpdateTableStatement::class)]
final class UpdateTableStatementTest extends TestCase
{
    public function testReturningDoesNotCreateAWherePredicate(): void
    {
        $schema = (new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('UPDATE OR IGNORE t SET id=1 RETURNING id');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertNull($statement->where);
        self::assertSame(ConstraintResponse::Ignore, $statement->onViolation);
        self::assertSame('t', $statement->target->declaration->name);
        self::assertCount(1, $statement->affectedTables());
        self::assertSame('UPDATE OR IGNORE "main"."t" SET "id" = 1 RETURNING "id" AS "id"', $statement->toString());
    }
}
