<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Catalog\CatalogCommands;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CatalogCommands::class)]
#[Medium]
final class CatalogCommandsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TEXT SEARCH DICTIONARY app.dict OWNER TO CURRENT_ROLE', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\ChangeObjectOwnerStatement::class, 'ALTER TEXT SEARCH DICTIONARY "app"."dict" OWNER TO CURRENT_ROLE'])]
    #[TestWith(['COMMENT ON ROUTINE r IS \'a\'\'b\'', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON ROUTINE "r" IS \'a\'\'b\''])]
    #[TestWith(['SECURITY LABEL ON SEQUENCE s IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SecurityLabelStatement::class, 'SECURITY LABEL ON SEQUENCE "s" IS NULL'])]
    #[TestWith(['DROP COLLATION IF EXISTS app.c', \SqlSemantics\Model\Statement\Definition\PostgreSql\Removal\DropSchemaObjectsStatement::class, 'DROP COLLATION IF EXISTS "app"."c"'])]
    #[TestWith(['ALTER TABLE t SET LOGGED', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" SET LOGGED'])]
    #[TestWith(['CREATE FOREIGN TABLE f () SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement::class, 'CREATE FOREIGN TABLE "public"."f"() SERVER "s"'])]
    public function testBindRoutesEachCatalogCommand(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TEXT SEARCH TEMPLATE app.tpl RENAME TO tpl2', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameObjectStatement::class, 'ALTER TEXT SEARCH TEMPLATE "app"."tpl" RENAME TO "tpl2"'])]
    #[TestWith(['ALTER DOMAIN d RENAME CONSTRAINT a TO b', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameDomainConstraintStatement::class, 'ALTER DOMAIN "d" RENAME CONSTRAINT "a" TO "b"'])]
    #[TestWith(['ALTER TYPE ty RENAME ATTRIBUTE a TO b RESTRICT', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameTypeAttributeStatement::class, 'ALTER TYPE "ty" RENAME ATTRIBUTE "a" TO "b" RESTRICT'])]
    #[TestWith(['ALTER POLICY p ON t RENAME TO q', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenamePolicyStatement::class, 'ALTER POLICY "p" ON "t" RENAME TO "q"'])]
    #[TestWith(['ALTER TABLE t RENAME TO u', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameRelationStatement::class, 'ALTER TABLE "t" RENAME TO "u"'])]
    public function testRenameSelectsTheFormByObjectClass(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER SEQUENCE IF EXISTS s SET SCHEMA app', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\SetRelationSchemaStatement::class, 'ALTER SEQUENCE IF EXISTS "s" SET SCHEMA "app"'])]
    #[TestWith(['ALTER STATISTICS st SET SCHEMA app', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SetObjectSchemaStatement::class, 'ALTER STATISTICS "st" SET SCHEMA "app"'])]
    #[TestWith(['ALTER FUNCTION f(int) SET SCHEMA app', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SetObjectSchemaStatement::class, 'ALTER FUNCTION "f"(integer) SET SCHEMA "app"'])]
    public function testSchemaMovesRelationsAndOtherObjects(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER PROCEDURE p DEPENDS ON EXTENSION e', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\AddExtensionDependencyStatement::class, 'ALTER PROCEDURE "p" DEPENDS ON EXTENSION "e"'])]
    #[TestWith(['ALTER MATERIALIZED VIEW mv NO DEPENDS ON EXTENSION e', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RemoveExtensionDependencyStatement::class, 'ALTER MATERIALIZED VIEW "mv" NO DEPENDS ON EXTENSION "e"'])]
    public function testDependencyReadsTheDirection(string $sql, string $class, string $expected): void
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
    #[TestWith(['SECURITY LABEL FOR p ON ROLE r IS \'x\'', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SecurityLabelStatement::class, 'SECURITY LABEL FOR "p" ON ROLE "r" IS \'x\''])]
    #[TestWith(['SECURITY LABEL ON PROCEDURE pr IS $$text$$', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\SecurityLabelStatement::class, 'SECURITY LABEL ON PROCEDURE "pr" IS $$text$$'])]
    public function testLabelReadsTheProviderAndText(string $sql, string $class, string $expected): void
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
    #[TestWith(['COMMENT ON COLUMN t.id IS E\'a\\\\b\'', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON COLUMN "t"."id" IS E\'a\\\\b\''])]
    #[TestWith(['COMMENT ON EXTENSION e IS NULL', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\CommentOnStatement::class, 'COMMENT ON EXTENSION "e" IS NULL'])]
    public function testTextKeepsTheSpellingOrNull(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    #[TestWith(['SECURITY LABEL ON INDEX ix IS \'x\''])]
    public function testLabelRejectsAnIndex(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::SecurityLabelTarget->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['ALTER COLLATION a.b.c RENAME TO d'])]
    public function testRenameRejectsAnOverQualifiedName(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        $binder->bind($sql, strict: false);
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER DOMAIN s.d RENAME CONSTRAINT a TO b', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameDomainConstraintStatement::class, 'ALTER DOMAIN "s"."d" RENAME CONSTRAINT "a" TO "b"'])]
    #[TestWith(['ALTER DOMAIN d RENAME TO e', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameObjectStatement::class, 'ALTER DOMAIN "d" RENAME TO "e"'])]
    #[TestWith(['ALTER TYPE s.ty RENAME ATTRIBUTE a TO b cascade', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameTypeAttributeStatement::class, 'ALTER TYPE "s"."ty" RENAME ATTRIBUTE "a" TO "b" CASCADE'])]
    #[TestWith(['ALTER TYPE ty RENAME ATTRIBUTE a TO b', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameTypeAttributeStatement::class, 'ALTER TYPE "ty" RENAME ATTRIBUTE "a" TO "b"'])]
    #[TestWith(['ALTER TYPE ty RENAME TO t2', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenameObjectStatement::class, 'ALTER TYPE "ty" RENAME TO "t2"'])]
    #[TestWith(['ALTER POLICY p ON c.s.t RENAME TO q', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RenamePolicyStatement::class, 'ALTER POLICY "p" ON "c"."s"."t" RENAME TO "q"'])]
    public function testRenameAcceptsTheQualificationOfEachForm(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
    }

    #[TestWith(['ALTER DOMAIN c.s.d RENAME CONSTRAINT a TO b'])]
    #[TestWith(['ALTER TYPE c.s.ty RENAME ATTRIBUTE a TO b'])]
    #[TestWith(['ALTER POLICY p ON x.c.s.t RENAME TO q'])]
    public function testRenameRejectsAnOverQualifiedOwner(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        $binder->bind($sql, strict: false);
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TRIGGER tr ON t DEPENDS ON EXTENSION e', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\AddExtensionDependencyStatement::class, 'ALTER TRIGGER "tr" ON "t" DEPENDS ON EXTENSION "e"'])]
    #[TestWith(['ALTER INDEX ix NO DEPENDS ON EXTENSION e', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\RemoveExtensionDependencyStatement::class, 'ALTER INDEX "ix" NO DEPENDS ON EXTENSION "e"'])]
    #[TestWith(['ALTER FUNCTION f() DEPENDS ON EXTENSION e', \SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog\AddExtensionDependencyStatement::class, 'ALTER FUNCTION "f"() DEPENDS ON EXTENSION "e"'])]
    public function testDependencyAcceptsEveryDependentObject(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
    }

    #[TestWith(['SECURITY LABEL ON SEQUENCE s IS null', 'SECURITY LABEL ON SEQUENCE "s" IS NULL'])]
    #[TestWith(['COMMENT ON TABLE t IS null', 'COMMENT ON TABLE "t" IS NULL'])]
    public function testTextReadsALowercaseNull(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false)->toString());
    }
}
