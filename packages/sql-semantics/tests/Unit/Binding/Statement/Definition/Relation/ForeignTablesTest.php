<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Relation\ForeignTables;
use SqlSemantics\Binding\TableResolver;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Relation\Foreign;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ForeignTables::class)]
#[Medium]
final class ForeignTablesTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE FOREIGN TABLE IF NOT EXISTS app.f (a integer OPTIONS (x \'y\') NOT NULL, b text) INHERITS (t) SERVER s OPTIONS (o \'p\')', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement::class, 'CREATE FOREIGN TABLE IF NOT EXISTS "app"."f"("a" integer OPTIONS("x" \'y\') NOT NULL, "b" text) INHERITS("t") SERVER "s" OPTIONS("o" \'p\')'])]
    public function testBindBindsOwnColumnsAndParents(string $sql, string $class, string $expected): void
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
    #[TestWith(['CREATE FOREIGN TABLE f (LIKE t INCLUDING ALL EXCLUDING STORAGE) SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement::class, 'CREATE FOREIGN TABLE "public"."f"(LIKE "t" INCLUDING ALL EXCLUDING STORAGE) SERVER "s"'])]
    public function testTemplateReadsSelections(string $sql, string $class, string $expected): void
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
    #[TestWith(['CREATE FOREIGN TABLE IF NOT EXISTS f PARTITION OF t FOR VALUES FROM (1) TO (10) SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement::class, 'CREATE FOREIGN TABLE IF NOT EXISTS "f" PARTITION OF "t" FOR VALUES FROM(1) TO(10) SERVER "s"'])]
    public function testPartitionReadsTheParentAndBound(string $sql, string $class, string $expected): void
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
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (CONSTRAINT c CHECK (n > 0)) DEFAULT SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement::class, 'CREATE FOREIGN TABLE "f" PARTITION OF "t"(CONSTRAINT "c" CHECK (("n" > 0))) DEFAULT SERVER "s"'])]
    public function testConstraintBindsTableConstraintsOfAPartition(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    #[TestWith(['CREATE FOREIGN TABLE f (a int) INHERITS (c.s.t) SERVER s', 'CREATE FOREIGN TABLE "public"."f"("a" integer) INHERITS("c"."s"."t") SERVER "s"'])]
    #[TestWith(['CREATE FOREIGN TABLE f (LIKE c.s.t including defaults) SERVER s', 'CREATE FOREIGN TABLE "public"."f"(LIKE "c"."s"."t" INCLUDING DEFAULTS) SERVER "s"'])]
    #[TestWith(['CREATE FOREIGN TABLE f (LIKE t EXCLUDING ALL) SERVER s', 'CREATE FOREIGN TABLE "public"."f"(LIKE "t" EXCLUDING ALL) SERVER "s"'])]
    #[TestWith(['CREATE FOREIGN TABLE f (a int, CHECK (f.a > 0)) SERVER s', 'CREATE FOREIGN TABLE "public"."f"("a" integer, CHECK (("f"."a" > 0))) SERVER "s"'])]
    #[TestWith(['CREATE FOREIGN TABLE app.f (a int, CHECK (app.f.a > 0)) SERVER s', 'CREATE FOREIGN TABLE "app"."f"("a" integer, CHECK (("app"."f"."a" > 0))) SERVER "s"'])]
    #[TestWith(['CREATE FOREIGN TABLE c.s.f PARTITION OF c.s.t DEFAULT SERVER s', 'CREATE FOREIGN TABLE "c"."s"."f" PARTITION OF "c"."s"."t" DEFAULT SERVER "s"'])]
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (CHECK (id > 0), n NOT NULL) DEFAULT SERVER s', 'CREATE FOREIGN TABLE "f" PARTITION OF "t"("n" WITH OPTIONS NOT NULL, CHECK (("id" > 0))) DEFAULT SERVER "s"'])]
    public function testBindWritesBackQualifiedNamesTemplatesAndPartitionElements(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        self::assertSame($expected, $binder->bind($sql, strict: false)->toString());
    }

    #[TestWith(['CREATE FOREIGN TABLE f (a int) INHERITS (x.c.s.t) SERVER s'])]
    #[TestWith(['CREATE FOREIGN TABLE f (LIKE x.c.s.t) SERVER s'])]
    #[TestWith(['CREATE FOREIGN TABLE x.c.s.f PARTITION OF t DEFAULT SERVER s'])]
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF x.c.s.t DEFAULT SERVER s'])]
    public function testBindRejectsOverQualifiedNames(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::CatalogObjectName->message());
        $binder->bind($sql, strict: false);
    }

    public function testBindRejectsAKeyOnAForeignTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ForeignTableConstraint->message());
        $binder->bind('CREATE FOREIGN TABLE f (a int PRIMARY KEY) SERVER s', strict: false);
    }

    public function testBindReturnsNullForAnotherStatement(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build();
        $node = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE f (a int)')->find('CreateStmt')[0];
        self::assertNull(ForeignTables::bind(new Origin('s0', $node, Dialect::PostgreSql), $node, new QueryContext(new TableResolver($schema, new Identifiers(Dialect::PostgreSql), ''))));
    }

    public function testTemplateReadsTheOrderedSelections(): void
    {
        $context = new QueryContext(new TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new Identifiers(Dialect::PostgreSql), ''));
        $like = (new DialectParser(Dialect::PostgreSql))->parse('CREATE FOREIGN TABLE f (LIKE s.t excluding all including comments) SERVER s')->find('TableLikeClause')[0];
        $template = ForeignTables::template($like, $context);
        self::assertSame(['s', 't'], $template->source->parts);
        self::assertSame([[Foreign\TemplateProperty::All, false], [Foreign\TemplateProperty::Comments, true]], array_map(static fn (Foreign\TemplateSelection $selection): array => [$selection->property, $selection->including], $template->selections));
    }
}
