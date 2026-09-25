<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Condition\SqlState;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\ErrorCode;
use SqlSemantics\Model\Definition\Routine\Stored\DeclaredDomain;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Program\ProgramDeclarations;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ProgramDeclarations::class)]
#[Medium]
final class ProgramDeclarationsTest extends TestCase
{
    public function testWriteWritesEveryDeclarationForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE PROCEDURE p() BEGIN DECLARE a, b INT DEFAULT 1; DECLARE dup CONDITION FOR 0x426; DECLARE c CURSOR FOR SELECT 1; DECLARE EXIT HANDLER FOR dup, SQLSTATE '42S02', SQLEXCEPTION BEGIN END; END");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(BlockStatement::class, $statement->body);
        self::assertSame(
            ['DECLARE `a`, `b` integer DEFAULT 1', 'DECLARE `dup` CONDITION FOR 0x426', 'DECLARE `c` CURSOR FOR SELECT 1', "DECLARE EXIT HANDLER FOR `dup`, SQLSTATE '42S02', SQLEXCEPTION BEGIN END"],
            array_map(static fn ($declaration): string => ProgramDeclarations::write($declaration)->toString(), $statement->body->declarations),
        );
    }

    public function testConditionWritesErrorNumbersAsWritten(): void
    {
        self::assertSame('0x426', ProgramDeclarations::condition(new ErrorCode('0x426'))->toString());
        self::assertSame("SQLSTATE '45000'", ProgramDeclarations::condition(new SqlState('45000'))->toString());
    }

    public function testDomainWritesTheCollation(): void
    {
        self::assertSame('text COLLATE `utf8mb4_bin`', ProgramDeclarations::domain(new DeclaredDomain(TypeDescriptor::builtin(Dialect::MySql, 'text'), 'utf8mb4_bin'))->toString());
    }

    public function testDomainWritesZerofillBeforeTheCollation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT ZEROFILL RETURN 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement::class, $statement);
        self::assertSame('integer UNSIGNED ZEROFILL', ProgramDeclarations::domain($statement->returns)->toString());
    }

    public function testAssignmentsSpellsSessionAfterAScopedItem(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) SET GLOBAL max_connections = 1, a = 2, SESSION wait_timeout = 3');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertInstanceOf(AssignmentStatement::class, $statement->body);
        self::assertSame('SET GLOBAL `max_connections` = 1, `a` = 2, SESSION `wait_timeout` = 3', ProgramDeclarations::assignments($statement->body)->toString());
    }
}
