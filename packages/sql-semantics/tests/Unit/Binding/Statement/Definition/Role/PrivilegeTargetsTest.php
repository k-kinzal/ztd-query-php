<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
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
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\TableTargets;
use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Role\PrivilegeTargets::class)]
#[Medium]
final class PrivilegeTargetsTest extends TestCase
{
    /**
     * @param class-string<SchemaObjectTargets|ServerObjectTargets|RoutineTargets|LargeObjectTargets|ParameterTargets|SchemaScopedTargets|TableTargets> $class
     */
    #[TestWith(['GRANT SELECT ON TABLE t TO a', TableTargets::class])]
    #[TestWith(['GRANT SELECT ON t TO a', TableTargets::class])]
    #[TestWith(['GRANT USAGE ON SEQUENCE s TO a', SchemaObjectTargets::class])]
    #[TestWith(['GRANT USAGE ON DOMAIN d TO a', SchemaObjectTargets::class])]
    #[TestWith(['GRANT USAGE ON TYPE ty TO a', SchemaObjectTargets::class])]
    #[TestWith(['GRANT CREATE ON DATABASE db TO a', ServerObjectTargets::class])]
    #[TestWith(['GRANT USAGE ON FOREIGN DATA WRAPPER w TO a', ServerObjectTargets::class])]
    #[TestWith(['GRANT USAGE ON FOREIGN SERVER s TO a', ServerObjectTargets::class])]
    #[TestWith(['GRANT USAGE ON LANGUAGE plpgsql TO a', ServerObjectTargets::class])]
    #[TestWith(['GRANT CREATE ON SCHEMA s TO a', ServerObjectTargets::class])]
    #[TestWith(['GRANT CREATE ON TABLESPACE ts TO a', ServerObjectTargets::class])]
    #[TestWith(['GRANT EXECUTE ON FUNCTION f TO a', RoutineTargets::class])]
    #[TestWith(['GRANT EXECUTE ON PROCEDURE p(int) TO a', RoutineTargets::class])]
    #[TestWith(['GRANT EXECUTE ON ROUTINE r TO a', RoutineTargets::class])]
    #[TestWith(['GRANT SELECT ON LARGE OBJECT 12 TO a', LargeObjectTargets::class])]
    #[TestWith(['GRANT SET ON PARAMETER work_mem TO a', ParameterTargets::class])]
    #[TestWith(['GRANT ALL ON ALL TABLES IN SCHEMA s TO a', SchemaScopedTargets::class])]
    #[TestWith(['GRANT USAGE ON ALL SEQUENCES IN SCHEMA s TO a', SchemaScopedTargets::class])]
    public function testReadClassifiesEveryObjectClass(string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind($sql);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertInstanceOf($class, $statement->target);
    }

    #[TestWith(['SEQUENCE', SchemaObjectClass::Sequence])]
    #[TestWith(['DOMAIN', SchemaObjectClass::Domain])]
    #[TestWith(['TYPE', SchemaObjectClass::Type])]
    public function testReadDistinguishesTheSchemaObjectClasses(string $keyword, SchemaObjectClass $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT USAGE ON ' . $keyword . ' app.x, "Y" TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $target = $statement->target;
        self::assertInstanceOf(SchemaObjectTargets::class, $target);
        self::assertSame($expected, $target->class);
        self::assertEquals([new QualifiedName(['app', 'x']), new QualifiedName(['Y'])], $target->names);
    }

    #[TestWith(['CREATE ON DATABASE', ServerObjectClass::Database])]
    #[TestWith(['USAGE ON FOREIGN DATA WRAPPER', ServerObjectClass::ForeignDataWrapper])]
    #[TestWith(['USAGE ON FOREIGN SERVER', ServerObjectClass::ForeignServer])]
    #[TestWith(['USAGE ON LANGUAGE', ServerObjectClass::Language])]
    #[TestWith(['CREATE ON SCHEMA', ServerObjectClass::Schema])]
    #[TestWith(['CREATE ON TABLESPACE', ServerObjectClass::Tablespace])]
    public function testReadDistinguishesTheServerObjectClasses(string $clause, ServerObjectClass $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT ' . $clause . ' x, "Y" TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $target = $statement->target;
        self::assertInstanceOf(ServerObjectTargets::class, $target);
        self::assertSame($expected, $target->class);
        self::assertSame(['x', 'Y'], $target->names);
    }

    #[TestWith(['FUNCTION', RoutineClass::Function])]
    #[TestWith(['PROCEDURE', RoutineClass::Procedure])]
    #[TestWith(['ROUTINE', RoutineClass::Routine])]
    public function testReadDistinguishesTheRoutineClasses(string $keyword, RoutineClass $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT EXECUTE ON ' . $keyword . ' f, g(), app.h(IN id integer, OUT message text) TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $target = $statement->target;
        self::assertInstanceOf(RoutineTargets::class, $target);
        self::assertSame($expected, $target->class);
        self::assertCount(3, $target->routines);
        self::assertEquals(new RoutineByName(new QualifiedName(['f'])), $target->routines[0]);
        self::assertEquals(new RoutineBySignature(new QualifiedName(['g']), []), $target->routines[1]);
        $signature = $target->routines[2];
        self::assertInstanceOf(RoutineBySignature::class, $signature);
        self::assertSame(['app', 'h'], $signature->name->parts);
        self::assertCount(2, $signature->parameters);
    }

    #[TestWith(['TABLES', SchemaScopedClass::Tables])]
    #[TestWith(['SEQUENCES', SchemaScopedClass::Sequences])]
    #[TestWith(['FUNCTIONS', SchemaScopedClass::Functions])]
    #[TestWith(['PROCEDURES', SchemaScopedClass::Procedures])]
    #[TestWith(['ROUTINES', SchemaScopedClass::Routines])]
    public function testReadDistinguishesTheSchemaScopedClasses(string $keyword, SchemaScopedClass $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT ALL ON ALL ' . $keyword . ' IN SCHEMA s, s2 TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $target = $statement->target;
        self::assertInstanceOf(SchemaScopedTargets::class, $target);
        self::assertSame($expected, $target->class);
        self::assertSame(['s', 's2'], $target->schemas);
    }

    public function testReadTreatsABareNameListAsTables(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind('GRANT ALL (id, a) ON t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $target = $statement->target;
        self::assertInstanceOf(TableTargets::class, $target);
        self::assertCount(1, $target->tables);
        self::assertSame(['public', 't'], $target->tables[0]->name->parts);
    }

    public function testListLocatesTheNameListOfEachClass(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $sequences = $binder->bind('GRANT USAGE, SELECT ON SEQUENCE s, app.s2 TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $sequences);
        $target = $sequences->target;
        self::assertInstanceOf(SchemaObjectTargets::class, $target);
        self::assertCount(2, $target->names);
        $parameters = $binder->bind('GRANT SET, ALTER SYSTEM ON PARAMETER work_mem, app.setting TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $parameters);
        $names = $parameters->target;
        self::assertInstanceOf(ParameterTargets::class, $names);
        self::assertEquals([new QualifiedName(['work_mem']), new QualifiedName(['app', 'setting'])], $names->parameters);
    }

    public function testTablesResolvesEachNameAgainstTheSchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)')))->bind('GRANT SELECT ON TABLE t, public.t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $target = $statement->target;
        self::assertInstanceOf(TableTargets::class, $target);
        self::assertCount(2, $target->tables);
        self::assertSame(['public', 't'], $target->tables[0]->name->parts);
        self::assertSame(['public', 't'], $target->tables[1]->name->parts);
        self::assertSame([], $statement->diagnostics);
    }

    public function testTablesDiagnosesUnknownTablesWhenNotStrict(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT SELECT ON u TO a', strict: false);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertCount(1, $statement->diagnostics);
        self::assertSame('unknown-table', $statement->diagnostics[0]->reason);
        $target = $statement->target;
        self::assertInstanceOf(TableTargets::class, $target);
        self::assertSame(['public', 'u'], $target->tables[0]->name->parts);
        self::assertSame('GRANT SELECT ON TABLE "public"."u" TO "a"', $statement->toString());
    }

    public function testTablesRejectsUnknownTablesWhenStrict(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(SemanticException::class);
        $binder->bind('GRANT SELECT ON u TO a');
    }

    public function testQualifiedReadsSchemaQualifiedNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT USAGE ON SEQUENCE s, app.s2, "Cat".app."S3" TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $target = $statement->target;
        self::assertInstanceOf(SchemaObjectTargets::class, $target);
        self::assertEquals([new QualifiedName(['s']), new QualifiedName(['app', 's2']), new QualifiedName(['Cat', 'app', 'S3'])], $target->names);
    }

    #[TestWith(['GRANT USAGE ON SEQUENCE a.b.c.d TO a'])]
    #[TestWith(['GRANT USAGE ON DOMAIN a.b.c.d TO a'])]
    public function testQualifiedRejectsOverlongNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RelationName->message());
        $binder->bind($sql);
    }

    #[TestWith(['GRANT SELECT ON t[1] TO a'])]
    #[TestWith(['GRANT SELECT ON a.b.c.d TO a'])]
    #[TestWith(['GRANT SELECT ON TABLE t[1] TO a'])]
    public function testRelationRejectsSubscriptsAndOverlongNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, a INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::RelationName->message());
        $binder->bind($sql);
    }

    public function testNamesReadsPlainNamesWithTheirCase(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $databases = $binder->bind('GRANT CREATE, CONNECT, TEMP ON DATABASE db1, "My DB" TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $databases);
        $target = $databases->target;
        self::assertInstanceOf(ServerObjectTargets::class, $target);
        self::assertSame(['db1', 'My DB'], $target->names);
        $schemas = $binder->bind('GRANT EXECUTE ON ALL PROCEDURES IN SCHEMA S, "S" TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $schemas);
        $scoped = $schemas->target;
        self::assertInstanceOf(SchemaScopedTargets::class, $scoped);
        self::assertSame(['s', 'S'], $scoped->schemas);
    }

    public function testObjectIdsReadsUnsignedObjectIdentifiers(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('GRANT SELECT, UPDATE ON LARGE OBJECT 12, 0000013, +7, -0, 4294967295 TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $target = $statement->target;
        self::assertInstanceOf(LargeObjectTargets::class, $target);
        self::assertSame([12, 13, 7, 0, 4294967295], $target->ids);
    }

    #[TestWith(['-1'])]
    #[TestWith(['1.5'])]
    #[TestWith(['4294967296'])]
    #[TestWith(['1e3'])]
    #[TestWith(['1, -2'])]
    public function testObjectIdsRejectsSignedFractionalAndOverflowingNumbers(string $ids): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::LargeObjectId->message());
        $binder->bind('GRANT SELECT ON LARGE OBJECT ' . $ids . ' TO a');
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerReadWritesEachLowercaseTarget(): array
    {
        return [
            [Dialect::PostgreSql, null, 'grant select on table t to a', [GrantPrivilegesStatement::class, 'GRANT SELECT ON TABLE "public"."t" TO "a"']],
            [Dialect::PostgreSql, null, 'grant usage on foreign data wrapper w to a', [GrantPrivilegesStatement::class, 'GRANT USAGE ON FOREIGN DATA WRAPPER "w" TO "a"']],
            [Dialect::PostgreSql, null, 'grant usage on foreign server s to a', [GrantPrivilegesStatement::class, 'GRANT USAGE ON FOREIGN SERVER "s" TO "a"']],
            [Dialect::PostgreSql, null, 'GRANT SELECT ON LARGE OBJECT 000000000001 TO a', [GrantPrivilegesStatement::class, 'GRANT SELECT ON LARGE OBJECT 1 TO "a"']],
            [Dialect::PostgreSql, null, 'grant select on all tables in schema public to a', [GrantPrivilegesStatement::class, 'GRANT SELECT ON ALL TABLES IN SCHEMA "public" TO "a"']],
            [Dialect::PostgreSql, null, 'grant execute on all functions in schema public to a', [GrantPrivilegesStatement::class, 'GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA "public" TO "a"']],
        ];
    }

    #[DataProvider('providerReadWritesEachLowercaseTarget')]
    public function testReadWritesEachLowercaseTarget(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, $statement->toString()]);
    }

    #[TestWith(['GRANT SELECT ON LARGE OBJECT 4294967296 TO a'])]
    #[TestWith(['GRANT SELECT ON a.b.c.d TO a'])]
    #[TestWith(['GRANT SELECT ON t[1] TO a'])]
    public function testRelationRejectsAnImpossibleTargetName(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
