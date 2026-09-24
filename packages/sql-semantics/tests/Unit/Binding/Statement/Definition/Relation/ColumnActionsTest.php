<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Relation\ColumnActions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnActions::class)]
#[Medium]
final class ColumnActionsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t ALTER id SET STORAGE plain, ALTER n DROP EXPRESSION', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ALTER COLUMN "id" SET STORAGE PLAIN, ALTER COLUMN "n" DROP EXPRESSION'])]
    public function testReadClassifiesAlterColumn(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ALTER id SET DEFAULT 1, ALTER id SET NOT NULL, ALTER id SET STATISTICS 5, ALTER id SET (n_distinct = 1), ALTER id SET COMPRESSION pglz', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ALTER COLUMN "id" SET DEFAULT 1, ALTER COLUMN "id" SET NOT NULL, ALTER COLUMN "id" SET STATISTICS 5, ALTER COLUMN "id" SET ("n_distinct" = 1), ALTER COLUMN "id" SET COMPRESSION pglz'])]
    public function testSetClassifiesSetForms(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ALTER id DROP DEFAULT, ALTER id DROP NOT NULL, ALTER id DROP EXPRESSION IF EXISTS, ALTER id DROP IDENTITY', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ALTER COLUMN "id" DROP DEFAULT, ALTER COLUMN "id" DROP NOT NULL, ALTER COLUMN "id" DROP EXPRESSION IF EXISTS, ALTER COLUMN "id" DROP IDENTITY'])]
    public function testDropPropertyClassifiesDropForms(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER INDEX ix ALTER COLUMN 3 SET STATISTICS -1', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER INDEX "ix" ALTER COLUMN 3 SET STATISTICS DEFAULT'])]
    #[TestWith(['ALTER TABLE t ALTER id SET STATISTICS 10000', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ALTER COLUMN "id" SET STATISTICS 10000'])]
    public function testStatisticsAddressesColumnsByNameOrPosition(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ALTER id TYPE numeric(10, 2) USING id * 1.0', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ALTER COLUMN "id" TYPE numeric(10, 2) USING("id" * 1.0)'])]
    public function testTypeReadsTheTypeCollationAndConversion(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t DROP COLUMN IF EXISTS n RESTRICT', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" DROP COLUMN IF EXISTS "n" RESTRICT'])]
    public function testDropReadsExistenceAndBehavior(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ADD c text COLLATE "C" DEFAULT \'x\' REFERENCES t (id)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD COLUMN "c" text COLLATE "C" DEFAULT \'x\' REFERENCES "t"("id") ON DELETE NO ACTION ON UPDATE NO ACTION'])]
    public function testAddReadsTheColumnDeclaration(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ADD COLUMN c integer CHECK (c > id)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD COLUMN "c" integer CHECK (("c" > "id"))'])]
    public function testExtendedBindsConstraintsAgainstTheAddedColumn(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    #[TestWith(['ALTER INDEX ix ALTER COLUMN 32768 SET STATISTICS 1'])]
    public function testStatisticsRejectsAPositionOutOfRange(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ColumnPosition->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['ALTER TABLE t ALTER id SET STATISTICS -2'])]
    public function testStatisticsRejectsATargetOutOfRange(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::StatisticsTarget->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['ALTER TABLE t ALTER id SET STORAGE fast'])]
    public function testSetRejectsAnUnknownStorage(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ColumnStorage->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['ALTER TABLE t ALTER id SET COMPRESSION zstd'])]
    public function testSetRejectsAnUnknownCompression(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ColumnCompression->message());
        $binder->bind($sql, strict: false);
    }
}
