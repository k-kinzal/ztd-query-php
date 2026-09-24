<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Body\ReturnStatement;
use SqlSemantics\Model\Definition\Routine\Stored\ExternalRoutineCode;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\ProgramInvariant;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ProgramInvariant::class)]
#[Medium]
final class ProgramInvariantTest extends TestCase
{
    public function testDefinitionAllowsEventIfNotExistsOnEveryRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
        ProgramInvariant::definition($statement->origin, new QualifiedName(['db', 'e']), true, ProgramKind::Event);
        $this->expectException(InvalidStructure::class);
        ProgramInvariant::definition($statement->origin, new QualifiedName(['db', 'p']), true, ProgramKind::Procedure);
    }

    public function testBodyRequiresMySql81ForExternalCode(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.1.0'))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
        ProgramInvariant::body($statement->origin, new ExternalRoutineCode('JAVASCRIPT', ''), ProgramKind::Procedure);
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.0.44'))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
        $this->expectException(InvalidStructure::class);
        ProgramInvariant::body($legacy->origin, new ExternalRoutineCode('JAVASCRIPT', ''), ProgramKind::Procedure);
    }

    public function testBodyChecksTheProgramStructure(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION f() RETURNS INT RETURN 1');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement::class, $statement);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
        $this->expectException(InvalidStructure::class);
        ProgramInvariant::body($statement->origin, $statement->body, ProgramKind::Procedure);
    }

    public function testDistinctRejectsNamesDifferingInCase(): void
    {
        ProgramInvariant::distinct(['a', 'b']);
        $this->expectException(InvalidStructure::class);
        ProgramInvariant::distinct(['a', 'A']);
    }

    public function testBeforeComparesKnownReleases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertTrue(ProgramInvariant::before($statement->origin, 'mysql-8.0.44'));
        self::assertFalse(ProgramInvariant::before($statement->origin, 'mysql-5.7.44'));
        self::assertFalse(ProgramInvariant::before(new Origin('s0', $statement->source, Dialect::MySql), 'mysql-9.1.0'));
        self::assertInstanceOf(BlockStatement::class, $statement->body);
    }
}
