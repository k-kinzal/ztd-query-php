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

    #[TestWith(['COMMENT ON TABLE a.b.t IS NULL', 'COMMENT ON TABLE "a"."b"."t" IS NULL'])]
    #[TestWith(['COMMENT ON COLUMN t.id IS NULL', 'COMMENT ON COLUMN "t"."id" IS NULL'])]
    #[TestWith(['COMMENT ON CONSTRAINT c ON t IS NULL', 'COMMENT ON CONSTRAINT "c" ON "t" IS NULL'])]
    #[TestWith(['COMMENT ON OPERATOR + (integer, text) IS NULL', 'COMMENT ON OPERATOR + (integer, text) IS NULL'])]
    #[TestWith(['COMMENT ON OPERATOR FAMILY app.f USING hash IS NULL', 'COMMENT ON OPERATOR FAMILY "app"."f" USING "hash" IS NULL'])]
    #[TestWith(['ALTER DOMAIN app.c RENAME TO d', 'ALTER DOMAIN "app"."c" RENAME TO "d"'])]
    #[TestWith(['COMMENT ON LARGE OBJECT 00000000001 IS NULL', 'COMMENT ON LARGE OBJECT 1 IS NULL'])]
    #[TestWith(["SECURITY LABEL FOR 'e' ON ROLE r IS NULL", 'SECURITY LABEL FOR "e" ON ROLE "r" IS NULL'])]
    #[TestWith(["SECURITY LABEL FOR 'u&' ON ROLE r IS NULL", 'SECURITY LABEL FOR "u&" ON ROLE "r" IS NULL'])]
    #[TestWith(["SECURITY LABEL FOR E'it''s\\x41' ON ROLE r IS NULL", 'SECURITY LABEL FOR "it\'sA" ON ROLE "r" IS NULL'])]
    #[TestWith(["SECURITY LABEL FOR U&'it''s' ON ROLE r IS NULL", 'SECURITY LABEL FOR "it\'s" ON ROLE "r" IS NULL'])]
    public function testReadAcceptsNamesWithinTheDepthOfTheirClass(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        self::assertSame($expected, $binder->bind($sql, strict: false)->toString());
    }

    #[TestWith(['COMMENT ON COLLATION a.b.c IS NULL', InputViolation::CatalogObjectName])]
    #[TestWith(['COMMENT ON TABLE a.b.c.t IS NULL', InputViolation::CatalogObjectName])]
    #[TestWith(['COMMENT ON COLUMN a.b.c.d.e IS NULL', InputViolation::CatalogObjectName])]
    #[TestWith(['COMMENT ON CONSTRAINT c ON DOMAIN a.b.d IS NULL', InputViolation::CatalogObjectName])]
    #[TestWith(['ALTER DOMAIN a.b.c RENAME TO d', InputViolation::CatalogObjectName])]
    #[TestWith(['COMMENT ON OPERATOR FAMILY a.b.f USING hash IS NULL', InputViolation::CatalogObjectName])]
    #[TestWith(['COMMENT ON OPERATOR a.b.+ (integer, text) IS NULL', InputViolation::CatalogObjectName])]
    #[TestWith(['COMMENT ON LARGE OBJECT +1 IS NULL', InputViolation::LargeObjectId])]
    #[TestWith(['COMMENT ON LARGE OBJECT 1.5 IS NULL', InputViolation::LargeObjectId])]
    #[TestWith(['COMMENT ON LARGE OBJECT 12345678901 IS NULL', InputViolation::LargeObjectId])]
    public function testReadRejectsNamesAndIdentifiersOutsideTheirClass(string $sql, InputViolation $violation): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage($violation->message());
        $binder->bind($sql, strict: false);
    }

    public function testNameReadsTheComponentsOfANode(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $node = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('COMMENT ON COLLATION app.c IS NULL'), ['any_name'])[0];
        self::assertSame(['app', 'c'], ObjectAddresses::name($node, $context, 2)->parts);
    }

    public function testMemberAddressesAColumnByItsPath(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('COMMENT ON COLUMN app.t.id IS NULL'), ['CommentStmt'])[0];
        $member = ObjectAddresses::member(\SqlSemantics\Model\Definition\Catalog\Kind\RelationMemberKind::Column, $source, Tree::outer($source, ['any_name']), $context);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Catalog\RelationMemberIdentity::class, $member);
        self::assertSame('id', $member->name);
        self::assertSame(['app', 't'], $member->relation->parts);
    }

    public function testSignatureReadsADeclaredType(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('COMMENT ON TYPE integer IS NULL'), ['CommentStmt'])[0];
        $address = ObjectAddresses::signature('TYPE', $source, [], $context);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Catalog\DeclaredTypeIdentity::class, $address);
        self::assertSame('integer', $address->type->name);
    }

    public function testSpecialReadsACast(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('COMMENT ON CAST (text AS integer) IS NULL'), ['CommentStmt'])[0];
        $types = [(new \SqlSemantics\Ast\TypeReader(Dialect::PostgreSql))->read(Tree::outer($source, ['Typename'])[0]), (new \SqlSemantics\Ast\TypeReader(Dialect::PostgreSql))->read(Tree::outer($source, ['Typename'])[1])];
        $address = ObjectAddresses::special('CAST', $source, $types, [], $context);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Catalog\CastIdentity::class, $address);
        self::assertSame('text', $address->source->name);
        self::assertSame('integer', $address->target->name);
    }

    public function testOperatorReadsBothOperandTypes(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('COMMENT ON OPERATOR app.+ (integer, text) IS NULL'), ['operator_with_argtypes'])[0];
        $operator = ObjectAddresses::operator($source, $context);
        self::assertSame(['app', '+'], $operator->name->parts);
        self::assertSame('integer', $operator->left?->name);
        self::assertSame('text', $operator->right?->name);
    }

    public function testLargeObjectReadsTheDigitsOfANumber(): void
    {
        $source = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('COMMENT ON LARGE OBJECT 0_42 IS NULL'), ['NumericOnly'])[0];
        self::assertSame(42, ObjectAddresses::largeObject($source)->id);
    }
}
