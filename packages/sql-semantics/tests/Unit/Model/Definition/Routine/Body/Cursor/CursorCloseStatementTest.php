<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Cursor\CursorCloseStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CursorCloseStatement::class)]
#[Medium]
final class CursorCloseStatementTest extends TestCase
{
    public function testNamesADeclaredCursor(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p() BEGIN DECLARE rows_c CURSOR FOR SELECT 1; CLOSE Rows_C; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertInstanceOf(CursorCloseStatement::class, $statement->body->statements[0]);
        self::assertSame('Rows_C', $statement->body->statements[0]->cursor);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRequiresACursorName(): void
    {
        $this->expectException(InvalidStructure::class);
        new CursorCloseStatement('');
    }

    public function testDiagnosesAnUndeclaredCursor(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() CLOSE c', strict: false);
    }
}
