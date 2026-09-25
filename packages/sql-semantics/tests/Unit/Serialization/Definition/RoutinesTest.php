<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Statement\Definition\PostgreSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Serialization\Definition\Routines::class)]
#[Medium]
final class RoutinesTest extends TestCase
{
    public function testWriteDoesNotClaimUnrelatedStatements(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        self::assertNull(\SqlSemantics\Serialization\Definition\Routines::write($statement));
    }

    public function testRoutineQuotesEveryIdentifierWithoutTurningItsContentsIntoSql(): void
    {
        $target = new Routine\RoutineByName(new \SqlSemantics\Model\Relation\QualifiedName(['a"b; DROP TABLE t']));
        self::assertSame('"a""b; DROP TABLE t"', \SqlSemantics\Serialization\Definition\Routines::routine($target)->toString());
    }

    public function testAggregatePreservesAZeroArgumentSignature(): void
    {
        $target = new Routine\ZeroArgumentAggregate(new \SqlSemantics\Model\Relation\QualifiedName(['count_rows']));
        self::assertSame('"count_rows"(*)', \SqlSemantics\Serialization\Definition\Routines::aggregate($target)->toString());
    }

    public function testParameterKeepsExplicitInputModesAndDeclaredTypeModifiers(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP PROCEDURE f(IN "some arg" numeric(10,2))');
        self::assertInstanceOf(PostgreSql\DropProceduresStatement::class, $statement);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[0]);
        self::assertSame('IN "some arg" numeric(10, 2)', \SqlSemantics\Serialization\Definition\Routines::parameter($statement->targets[0]->parameters[0])->toString());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testWriteSpellsTheMySqlFunctionRemovalWithItsGuard(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP FUNCTION IF EXISTS db.f');
        self::assertSame('DROP FUNCTION IF EXISTS `db`.`f`', \SqlSemantics\Serialization\Definition\Routines::write($statement)?->toString());
    }

    public function testWriteSpellsTheMySqlProcedureRemovalWithoutAGuard(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('DROP PROCEDURE p');
        self::assertSame('DROP PROCEDURE `p`', \SqlSemantics\Serialization\Definition\Routines::write($statement)?->toString());
    }

    public function testWriteSpellsEveryPostgreSqlFunctionTargetAndTheBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP FUNCTION IF EXISTS f, g(int) CASCADE');
        self::assertSame('DROP FUNCTION IF EXISTS "f", "g"(integer) CASCADE', \SqlSemantics\Serialization\Definition\Routines::write($statement)?->toString());
    }

    public function testWriteSpellsThePostgreSqlProcedureRemoval(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP PROCEDURE p, q()');
        self::assertSame('DROP PROCEDURE "p", "q"()', \SqlSemantics\Serialization\Definition\Routines::write($statement)?->toString());
    }

    public function testWriteSpellsThePostgreSqlRoutineRemoval(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP ROUTINE r() RESTRICT');
        self::assertSame('DROP ROUTINE "r"() RESTRICT', \SqlSemantics\Serialization\Definition\Routines::write($statement)?->toString());
    }

    public function testWriteSpellsEveryAggregateSignatureForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP AGGREGATE IF EXISTS a(int, text), b(int ORDER BY text), c(*)');
        self::assertSame('DROP AGGREGATE IF EXISTS "a"(integer, text), "b"(integer ORDER BY text), "c"(*)', \SqlSemantics\Serialization\Definition\Routines::write($statement)?->toString());
    }

    public function testParameterSpellsTheColumnTypeReferenceAndTheSetMarker(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(c int)')))->bind('DROP FUNCTION f(OUT x t.c%TYPE, VARIADIC y int[], SETOF int)');
        self::assertSame('DROP FUNCTION "f"(OUT "x" "t"."c" %TYPE, VARIADIC "y" integer [], SETOF integer)', \SqlSemantics\Serialization\Definition\Routines::write($statement)?->toString());
    }
}
