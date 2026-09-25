<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\CursorDeclaration;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CursorDeclaration::class)]
#[Medium]
final class CursorDeclarationTest extends TestCase
{
    public function testBindsTheQueryWithLocalVariables(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind('CREATE PROCEDURE p(k INT) BEGIN DECLARE c CURSOR FOR SELECT n FROM t WHERE n > k; END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertInstanceOf(CursorDeclaration::class, $statement->body->declarations[0]);
        self::assertSame('n', $statement->body->declarations[0]->query->resultColumns()[0]->name);
        self::assertSame('CREATE PROCEDURE `p`(IN `k` integer) BEGIN DECLARE `c` CURSOR FOR SELECT `n` AS `n` FROM `t` WHERE (`n` > `k`); END', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAnotherDialectQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        new CursorDeclaration('c', $query);
    }

    public function testDiagnosesACursorQueryWithInto(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1 INTO @x; END', strict: false);
    }
}
