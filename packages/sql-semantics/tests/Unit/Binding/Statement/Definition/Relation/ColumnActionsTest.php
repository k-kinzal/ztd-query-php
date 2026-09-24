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

    #[TestWith(['ALTER TABLE t ALTER id SET GENERATED ALWAYS', 'ALTER TABLE "t" ALTER COLUMN "id" SET GENERATED ALWAYS'])]
    #[TestWith(['ALTER TABLE t ALTER id ADD GENERATED ALWAYS AS IDENTITY', 'ALTER TABLE "t" ALTER COLUMN "id" ADD GENERATED ALWAYS AS IDENTITY'])]
    #[TestWith(['ALTER TABLE t ALTER id RESET (n_distinct)', 'ALTER TABLE "t" ALTER COLUMN "id" RESET("n_distinct")'])]
    #[TestWith(["ALTER FOREIGN TABLE t ALTER id OPTIONS (ADD a 'b')", 'ALTER FOREIGN TABLE "t" ALTER COLUMN "id" OPTIONS(ADD "a" \'b\')'])]
    #[TestWith(['ALTER TABLE t ALTER n SET EXPRESSION AS (id + 1)', 'ALTER TABLE "t" ALTER COLUMN "n" SET EXPRESSION AS(("id" + 1))'])]
    #[TestWith(['ALTER TABLE t ALTER id SET DATA TYPE text', 'ALTER TABLE "t" ALTER COLUMN "id" TYPE text'])]
    #[TestWith(['ALTER TABLE t ALTER id SET COMPRESSION "PGLZ"', 'ALTER TABLE "t" ALTER COLUMN "id" SET COMPRESSION pglz'])]
    #[TestWith(['ALTER TABLE t ALTER id set statistics default', 'ALTER TABLE "t" ALTER COLUMN "id" SET STATISTICS DEFAULT'])]
    #[TestWith(['ALTER INDEX ix ALTER COLUMN 1_0 SET STATISTICS 5', 'ALTER INDEX "ix" ALTER COLUMN 10 SET STATISTICS 5'])]
    #[TestWith(['ALTER INDEX ix ALTER COLUMN 000000001 SET STATISTICS 5', 'ALTER INDEX "ix" ALTER COLUMN 1 SET STATISTICS 5'])]
    #[TestWith(['ALTER INDEX ix ALTER COLUMN 1 SET STATISTICS 5', 'ALTER INDEX "ix" ALTER COLUMN 1 SET STATISTICS 5'])]
    #[TestWith(['ALTER INDEX ix ALTER COLUMN 32767 SET STATISTICS 5', 'ALTER INDEX "ix" ALTER COLUMN 32767 SET STATISTICS 5'])]
    #[TestWith(['ALTER TABLE t ALTER id TYPE text COLLATE app.c', 'ALTER TABLE "t" ALTER COLUMN "id" TYPE text COLLATE "app"."c"'])]
    #[TestWith(['ALTER TABLE t DROP COLUMN n cascade', 'ALTER TABLE "t" DROP COLUMN "n" CASCADE'])]
    #[TestWith(["ALTER FOREIGN TABLE t ADD COLUMN c integer OPTIONS (a 'b')", 'ALTER FOREIGN TABLE "t" ADD COLUMN "c" integer OPTIONS("a" \'b\')'])]
    #[TestWith(['ALTER TABLE t ADD COLUMN IF NOT EXISTS c integer', 'ALTER TABLE "t" ADD COLUMN IF NOT EXISTS "c" integer'])]
    public function testReadBindsEachColumnActionForm(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        self::assertSame($expected, $binder->bind($sql, strict: false)->toString());
    }

    public function testAddResolvesConstraintsAgainstTheExistingAndAddedColumns(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        self::assertSame('ALTER TABLE "t" ADD COLUMN "c" integer CHECK (("c" > "id"))', $binder->bind('ALTER TABLE t ADD COLUMN c integer CHECK (c > id)')->toString());
    }

    #[TestWith(['ALTER INDEX ix ALTER COLUMN 0 SET STATISTICS 5', InputViolation::ColumnPosition])]
    #[TestWith(['ALTER TABLE t ALTER id TYPE text COLLATE a.b.c', InputViolation::CatalogObjectName])]
    public function testReadRejectsPositionsAndCollationsOutOfRange(string $sql, InputViolation $violation): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage($violation->message());
        $binder->bind($sql, strict: false);
    }

    public function testDropPropertyDropsTheNotNullRequirement(): void
    {
        $command = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ALTER id DROP NOT NULL'), ['alter_table_cmd'])[0];
        $action = ColumnActions::dropProperty('id', $command, ['DROP', 'NOT', 'NULL']);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Relation\Column\SetColumnNullability::class, $action);
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $action->nullability);
    }

    public function testStatisticsReadsTheTargetOfANamedColumn(): void
    {
        $command = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ALTER id SET STATISTICS 7'), ['alter_table_cmd'])[0];
        $statistics = ColumnActions::statistics('id', \SqlSemantics\Ast\Tree::outer($command, ['ColId'])[0], $command);
        self::assertSame('id', $statistics->column);
        self::assertSame(7, $statistics->target);
    }

    public function testSetReadsTheNotNullRequirement(): void
    {
        $identifiers = new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql);
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), $identifiers, 'public'));
        $command = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ALTER id SET NOT NULL'), ['alter_table_cmd'])[0];
        $action = ColumnActions::set('id', \SqlSemantics\Ast\Tree::outer($command, ['ColId'])[0], $command, ['SET', 'NOT', 'NULL'], new \SqlSemantics\Binding\Scope($identifiers, queries: $context), $context);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Relation\Column\SetColumnNullability::class, $action);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $action->nullability);
    }

    public function testTypeReadsTheTypeAndCollation(): void
    {
        $identifiers = new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql);
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), $identifiers, 'public'));
        $command = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ALTER id TYPE text COLLATE "C"'), ['alter_table_cmd'])[0];
        $change = ColumnActions::type('id', $command, new \SqlSemantics\Binding\Scope($identifiers, queries: $context), $context);
        self::assertSame('text', $change->type->name);
        self::assertSame(['C'], $change->collation?->parts);
        self::assertNull($change->using);
    }

    public function testExtendedAppendsTheNewColumnToTheDeclaration(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)');
        $identifiers = new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql);
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver($schema, $identifiers, 'public'));
        $source = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('ALTER TABLE t ADD COLUMN c integer');
        $column = new \SqlSemantics\Schema\ColumnDefinition('c', $schema->tables[0]->columns[0]->type, \SqlSemantics\Type\Nullability::MaybeNull, $source);
        $relation = new \SqlSemantics\Model\Relation\TableReference('t', 't', $schema->tables[0], new \SqlSemantics\Model\Relation\QualifiedName(['t']), null, $source);
        $scope = ColumnActions::extended($relation, $column, $source, $context);
        self::assertCount(1, $scope->relations);
        self::assertSame(['id', 'n', 'c'], array_map(static fn (\SqlSemantics\Schema\ColumnDefinition $definition): string => $definition->name, $scope->relations[0]->declaration->columns));
        self::assertInstanceOf(\SqlSemantics\Model\Relation\TableReference::class, $scope->relations[0]);
        self::assertSame(['public', 't'], $scope->relations[0]->name->parts);
        self::assertSame($context, $scope->queries);
    }
}
