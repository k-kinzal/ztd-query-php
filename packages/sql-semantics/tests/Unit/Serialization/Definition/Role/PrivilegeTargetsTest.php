<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\LargeObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ParameterTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\RoutineTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaScopedTargets;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\ServerObjectTargets;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Role\PrivilegeTargets;

#[CoversClass(PrivilegeTargets::class)]
#[Medium]
final class PrivilegeTargetsTest extends TestCase
{
    #[TestWith(['GRANT SELECT ON t TO a', 'TABLE "public"."t"'])]
    #[TestWith(['GRANT SELECT ON TABLE t, public.t TO a', 'TABLE "public"."t", "public"."t"'])]
    #[TestWith(['GRANT USAGE ON SEQUENCE s, app.s2 TO a', 'SEQUENCE "s", "app"."s2"'])]
    #[TestWith(['GRANT USAGE ON DOMAIN d TO a', 'DOMAIN "d"'])]
    #[TestWith(['GRANT USAGE ON TYPE ty TO a', 'TYPE "ty"'])]
    #[TestWith(['GRANT CREATE, CONNECT, TEMP ON DATABASE db1, db2 TO a', 'DATABASE "db1", "db2"'])]
    #[TestWith(['GRANT USAGE ON FOREIGN DATA WRAPPER w TO a', 'FOREIGN DATA WRAPPER "w"'])]
    #[TestWith(['GRANT USAGE ON FOREIGN SERVER s TO a', 'FOREIGN SERVER "s"'])]
    #[TestWith(['GRANT USAGE ON LANGUAGE plpgsql TO a', 'LANGUAGE "plpgsql"'])]
    #[TestWith(['GRANT CREATE ON SCHEMA s TO a', 'SCHEMA "s"'])]
    #[TestWith(['GRANT CREATE ON TABLESPACE ts TO a', 'TABLESPACE "ts"'])]
    #[TestWith(['GRANT SELECT, UPDATE ON LARGE OBJECT 12, 0000013 TO a', 'LARGE OBJECT 12, 13'])]
    #[TestWith(['GRANT SET, ALTER SYSTEM ON PARAMETER work_mem, app.setting TO a', 'PARAMETER "work_mem", "app"."setting"'])]
    #[TestWith(['GRANT EXECUTE ON FUNCTION f, g(), app.h(IN id integer, OUT message text) TO a', 'FUNCTION "f", "g"(), "app"."h"(IN "id" integer, OUT "message" text)'])]
    #[TestWith(['GRANT EXECUTE ON PROCEDURE p(int) TO a', 'PROCEDURE "p"(integer)'])]
    #[TestWith(['GRANT EXECUTE ON ROUTINE r TO a', 'ROUTINE "r"'])]
    #[TestWith(['GRANT ALL ON ALL TABLES IN SCHEMA s, s2 TO a', 'ALL TABLES IN SCHEMA "s", "s2"'])]
    #[TestWith(['GRANT USAGE ON ALL SEQUENCES IN SCHEMA s TO a', 'ALL SEQUENCES IN SCHEMA "s"'])]
    #[TestWith(['GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA s TO a', 'ALL FUNCTIONS IN SCHEMA "s"'])]
    #[TestWith(['GRANT EXECUTE ON ALL PROCEDURES IN SCHEMA s TO a', 'ALL PROCEDURES IN SCHEMA "s"'])]
    #[TestWith(['GRANT EXECUTE ON ALL ROUTINES IN SCHEMA s TO a', 'ALL ROUTINES IN SCHEMA "s"'])]
    public function testTargetWritesEachClassWithItsKeywordAndStaysFixedAcrossBinding(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame($expected, PrivilegeTargets::target($statement->target)->toString());
        self::assertStringContainsString(' ON ' . $expected . ' TO ', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(GrantPrivilegesStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
        self::assertSame($expected, PrivilegeTargets::target($rebound->target)->toString());
    }

    public function testTargetWritesHandBuiltTargetsWithQuotedNames(): void
    {
        self::assertSame('LARGE OBJECT 1, 4294967295', PrivilegeTargets::target(new LargeObjectTargets([1, 4294967295]))->toString());
        self::assertSame('FOREIGN DATA WRAPPER "w"', PrivilegeTargets::target(new ServerObjectTargets(ServerObjectClass::ForeignDataWrapper, ['w']))->toString());
        self::assertSame('DATABASE "My DB"', PrivilegeTargets::target(new ServerObjectTargets(ServerObjectClass::Database, ['My DB']))->toString());
        self::assertSame('ALL PROCEDURES IN SCHEMA "s"', PrivilegeTargets::target(new SchemaScopedTargets(SchemaScopedClass::Procedures, ['s']))->toString());
        self::assertSame('PARAMETER "app"."x"', PrivilegeTargets::target(new ParameterTargets([new QualifiedName(['app', 'x'])]))->toString());
        self::assertSame('DOMAIN "d", "s"."e"', PrivilegeTargets::target(new SchemaObjectTargets(SchemaObjectClass::Domain, [new QualifiedName(['d']), new QualifiedName(['s', 'e'])]))->toString());
        self::assertSame('ROUTINE "f", "g"()', PrivilegeTargets::target(new RoutineTargets(RoutineClass::Routine, [new RoutineByName(new QualifiedName(['f'])), new RoutineBySignature(new QualifiedName(['g']), [])]))->toString());
    }

    public function testQualifiedQuotesEachPart(): void
    {
        self::assertSame('"app"."s""2"', PrivilegeTargets::qualified(new QualifiedName(['app', 's"2']))->toString());
        self::assertSame('"Cat"."app"."t"', PrivilegeTargets::qualified(new QualifiedName(['Cat', 'app', 't']))->toString());
    }

    public function testNamesQuotesAndSeparatesEachName(): void
    {
        self::assertSame('"a", "b c"', PrivilegeTargets::names(['a', 'b c'])->toString());
        self::assertSame('"x""y"', PrivilegeTargets::names(['x"y'])->toString());
    }
}
