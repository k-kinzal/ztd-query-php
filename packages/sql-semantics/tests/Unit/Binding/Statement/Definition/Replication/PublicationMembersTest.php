<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Replication\PublicationMembers::class)]
#[Medium]
final class PublicationMembersTest extends TestCase
{
    public function testReadContinuesTheKindOfThePreviousObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLE t, public.t2, TABLES IN SCHEMA s, "S", CURRENT_SCHEMA', strict: false);
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        self::assertInstanceOf(Operand\PublishedTable::class, $statement->objects[1]);
        self::assertInstanceOf(Operand\PublishedSchema::class, $statement->objects[3]);
        self::assertInstanceOf(Operand\PublishedCurrentSchema::class, $statement->objects[4]);
    }

    #[TestWith(['CREATE PUBLICATION p FOR t'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLES IN SCHEMA s, x (a)'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLES IN SCHEMA s, x WHERE (true)'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLES IN SCHEMA s, x.y'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLE t, CURRENT_SCHEMA'])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLE t (a, a)'])]
    public function testReadDiagnosesAnImpossibleList(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PublicationObject->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind($sql);
    }

    #[TestWith(['TABLE ONLY t', true])]
    #[TestWith(['TABLE t, ONLY (t)', true])]
    #[TestWith(['TABLE t *', false])]
    public function testTableRetainsDescendantExclusion(string $objects, bool $only): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR ' . $objects);
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        $table = $statement->objects[count($statement->objects) - 1];
        self::assertInstanceOf(Operand\PublishedTable::class, $table);
        self::assertSame($only, $table->table instanceof \SqlSemantics\Model\Relation\OnlyTableReference);
    }

    public function testSchemaFoldsAnUnquotedName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLES IN SCHEMA Sales');
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        self::assertEquals([new Operand\PublishedSchema('sales')], $statement->objects);
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindReadsContinuedPublicationObjects')]
    public function testBindReadsContinuedPublicationObjects(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindReadsContinuedPublicationObjects(): iterable
    {
        return [
            'create publication p for table t, s.u (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'create publication p for table t, s.u', 'CREATE PUBLICATION "p" FOR TABLE "public"."t", TABLE "s"."u"'],
            'create publication p for table t (a, b) where (a > 1), only s.u (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'create publication p for table t (a, b) where (a > 1), only s.u', 'CREATE PUBLICATION "p" FOR TABLE "public"."t"("a", "b") WHERE (("a" > 1)), TABLE ONLY "s"."u"'],
            'create publication p for table only t, u2 (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'create publication p for table only t, u2', 'CREATE PUBLICATION "p" FOR TABLE ONLY "public"."t", TABLE "public"."u2"'],
            'create publication p for table t, s.u (a) where (a > 0) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'create publication p for table t, s.u (a) where (a > 0)', 'CREATE PUBLICATION "p" FOR TABLE "public"."t", TABLE "s"."u"("a") WHERE (("a" > 0))'],
            'create publication p for table t, s.u where (a > 0) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'create publication p for table t, s.u where (a > 0)', 'CREATE PUBLICATION "p" FOR TABLE "public"."t", TABLE "s"."u" WHERE (("a" > 0))'],
            'create publication p for tables in schema s, current_schema (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'create publication p for tables in schema s, current_schema', 'CREATE PUBLICATION "p" FOR TABLES IN SCHEMA "s", TABLES IN SCHEMA CURRENT_SCHEMA'],
            'ALTER PUBLICATION p ADD TABLE t, s.u (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'ALTER PUBLICATION p ADD TABLE t, s.u', 'ALTER PUBLICATION "p" ADD TABLE "public"."t", TABLE "s"."u"'],
            'ALTER PUBLICATION p SET TABLE ONLY t (a), s.u (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'ALTER PUBLICATION p SET TABLE ONLY t (a), s.u', 'ALTER PUBLICATION "p" SET TABLE ONLY "public"."t"("a"), TABLE "s"."u"'],
            'ALTER PUBLICATION p DROP TABLES IN SCHEMA s (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(a INT, b INT)', 'CREATE SCHEMA s', 'CREATE TABLE s.u(a INT)'], 'ALTER PUBLICATION p DROP TABLES IN SCHEMA s', 'ALTER PUBLICATION "p" DROP TABLES IN SCHEMA "s"'],
        ];
    }
}
