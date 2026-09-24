<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ObjectAddresses::class)]
#[Medium]
final class ObjectAddressesTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['COMMENT ON FOREIGN DATA WRAPPER w IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON FOREIGN DATA WRAPPER "w" IS NULL'])]
    #[TestWith(['COMMENT ON INDEX app.ix IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON INDEX "app"."ix" IS NULL'])]
    #[TestWith(['COMMENT ON TEXT SEARCH PARSER p IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON TEXT SEARCH PARSER "p" IS NULL'])]
    #[TestWith(['COMMENT ON RULE r ON t IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON RULE "r" ON "t" IS NULL'])]
    public function testReadBuildsTheIdentityOfEachClass(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE ONLY t RENAME TO u', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameRelationStatement::class, 'ALTER TABLE ONLY "t" RENAME TO "u"'])]
    #[TestWith(['ALTER TABLE t * RENAME TO u', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameRelationStatement::class, 'ALTER TABLE "t" RENAME TO "u"'])]
    public function testRelationIgnoresOnlyMarkers(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['COMMENT ON COLLATION app.c IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON COLLATION "app"."c" IS NULL'])]
    public function testNameReadsQualifiedNames(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['COMMENT ON COLUMN db.app.t.id IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON COLUMN "db"."app"."t"."id" IS NULL'])]
    #[TestWith(['COMMENT ON CONSTRAINT c ON DOMAIN app.d IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON CONSTRAINT "c" ON DOMAIN "app"."d" IS NULL'])]
    #[TestWith(['COMMENT ON TRIGGER tr ON app.t IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON TRIGGER "tr" ON "app"."t" IS NULL'])]
    public function testMemberReadsColumnsAndOtherMembers(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['COMMENT ON DOMAIN d IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON DOMAIN "d" IS NULL'])]
    #[TestWith(['COMMENT ON FUNCTION f IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON FUNCTION "f" IS NULL'])]
    #[TestWith(['COMMENT ON OPERATOR FAMILY f USING hash IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON OPERATOR FAMILY "f" USING "hash" IS NULL'])]
    public function testSignatureReadsTypesRoutinesAndOperatorSets(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['COMMENT ON AGGREGATE a(ORDER BY integer) IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON AGGREGATE "a"(ORDER BY integer) IS NULL'])]
    #[TestWith(['COMMENT ON OPERATOR ~ (NONE, integer) IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON OPERATOR ~ (NONE, integer) IS NULL'])]
    #[TestWith(['COMMENT ON CAST (text AS integer) IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON CAST(text AS integer) IS NULL'])]
    #[TestWith(['COMMENT ON TRANSFORM FOR integer LANGUAGE sql IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON TRANSFORM FOR integer LANGUAGE "sql" IS NULL'])]
    #[TestWith(['COMMENT ON LARGE OBJECT 1_000 IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON LARGE OBJECT 1000 IS NULL'])]
    public function testSpecialReadsAggregatesOperatorsCastsTransformsAndLargeObjects(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['COMMENT ON OPERATOR app.! (NONE, bigint) IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON OPERATOR "app".! (NONE, bigint) IS NULL'])]
    public function testOperatorReadsPrefixOperators(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['COMMENT ON LARGE OBJECT 4294967295 IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON LARGE OBJECT 4294967295 IS NULL'])]
    public function testLargeObjectReadsTheIdentifier(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['SECURITY LABEL FOR \'it\'\'s\' ON ROLE r IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SecurityLabelStatement::class, 'SECURITY LABEL FOR "it\'s" ON ROLE "r" IS NULL'])]
    #[TestWith(['SECURITY LABEL FOR $$p$$ ON ROLE r IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SecurityLabelStatement::class, 'SECURITY LABEL FOR "p" ON ROLE "r" IS NULL'])]
    #[TestWith(['SECURITY LABEL FOR "P" ON ROLE r IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SecurityLabelStatement::class, 'SECURITY LABEL FOR "P" ON ROLE "r" IS NULL'])]
    public function testProviderDecodesQuotedProviders(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    #[TestWith(['COMMENT ON OPERATOR + (integer) IS NULL'])]
    public function testOperatorRejectsAMissingOperand(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::OperatorSignature->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['COMMENT ON LARGE OBJECT 4294967296 IS NULL'])]
    public function testLargeObjectRejectsAnIdentifierOutOfRange(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::LargeObjectId->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['COMMENT ON COLUMN id IS NULL'])]
    public function testMemberRejectsAnUnqualifiedColumn(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        $binder->bind($sql, strict: false);
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['SECURITY LABEL FOR p ON MATERIALIZED VIEW mv IS NULL', 'SecLabelStmt', 'MATERIALIZED VIEW'])]
    #[TestWith(['ALTER PROCEDURAL LANGUAGE l RENAME TO m', 'RenameStmt', 'LANGUAGE'])]
    #[TestWith(['COMMENT ON TEXT SEARCH DICTIONARY d IS NULL', 'CommentStmt', 'TEXT SEARCH DICTIONARY'])]
    public function testObjectClassReadsTheKeywordsAfterTheVerb(string $sql, string $rule, string $class): void
    {
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse($sql), [$rule])[0];
        self::assertSame($class, ObjectAddresses::objectClass($node));
    }

    public function testWordsUppercasesEveryToken(): void
    {
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('comment on schema app is null'), ['CommentStmt'])[0];
        self::assertSame(['COMMENT', 'ON', 'SCHEMA', 'APP', 'IS', 'NULL'], ObjectAddresses::words($node));
    }

    #[TestWith(['SECURITY LABEL FOR p ON ROLE r IS NULL', 'SecLabelStmt', 5])]
    #[TestWith(['SECURITY LABEL ON ROLE r IS NULL', 'SecLabelStmt', 3])]
    #[TestWith(['COMMENT ON ROLE r IS NULL', 'CommentStmt', 2])]
    #[TestWith(['ALTER ROUTINE r RENAME TO s', 'RenameStmt', 1])]
    public function testStartSkipsTheVerbAndProvider(string $sql, string $rule, int $start): void
    {
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse($sql), [$rule])[0];
        self::assertSame($start, ObjectAddresses::start($node, ObjectAddresses::words($node)));
    }
}
