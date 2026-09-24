<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(BlockStatement::class)]
#[Medium]
final class BlockStatementTest extends TestCase
{
    public function testKeepsDeclarationsBeforeStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind('CREATE PROCEDURE p() b: BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT n FROM t; DECLARE CONTINUE HANDLER FOR NOT FOUND SET x = 1; OPEN c; END b');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertCount(3, $statement->body->declarations);
        self::assertCount(1, $statement->body->statements);
        self::assertSame('CREATE PROCEDURE `p`() `b` : BEGIN DECLARE `x` integer; DECLARE `c` CURSOR FOR SELECT `n` AS `n` FROM `t`; DECLARE CONTINUE HANDLER FOR NOT FOUND SET `x` = 1; OPEN `c`; END `b`', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testRejectsAnEmptyLabel(): void
    {
        $this->expectException(InvalidStructure::class);
        new BlockStatement('');
    }

    public function testDiagnosesAVariableAfterACursor(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ProgramDeclaration->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; DECLARE x INT; END', strict: false);
    }
}
