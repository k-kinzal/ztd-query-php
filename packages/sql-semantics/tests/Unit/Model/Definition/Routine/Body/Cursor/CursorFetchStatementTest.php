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
use SqlSemantics\Model\Definition\Routine\Body\Cursor\CursorFetchStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CursorFetchStatement::class)]
#[Medium]
final class CursorFetchStatementTest extends TestCase
{
    public function testStoresTheRowInDeclaredVariables(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('CREATE PROCEDURE p() BEGIN DECLARE a, b INT; DECLARE c CURSOR FOR SELECT 1, 2; FETCH FROM c INTO a, b; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        $fetch = $statement->body->statements[0];
        self::assertInstanceOf(CursorFetchStatement::class, $fetch);
        self::assertSame(['a', 'b'], array_map(static fn ($target): string => $target->variable->name, $fetch->targets));
        self::assertSame('CREATE PROCEDURE `p`() BEGIN DECLARE `a`, `b` integer; DECLARE `c` CURSOR FOR SELECT 1, 2; FETCH `c` INTO `a`, `b`; END', $statement->toString());
    }

    public function testRequiresATarget(): void
    {
        $this->expectException(InvalidStructure::class);
        new CursorFetchStatement('c', []);
    }

    public function testDiagnosesAnUndeclaredTarget(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramObject->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; FETCH c INTO missing; END', strict: false);
    }
}
