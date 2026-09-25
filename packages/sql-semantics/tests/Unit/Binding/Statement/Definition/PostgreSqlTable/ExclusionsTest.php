<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\PostgreSqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\PostgreSqlTable\Exclusions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Exclusions::class)]
#[Medium]
final class ExclusionsTest extends TestCase
{
    public function testReadBindsEveryExcludeConstraintInOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TABLE t (a INTEGER, b INTEGER, CONSTRAINT e EXCLUDE USING gist (a WITH =) WHERE (a > 0) DEFERRABLE, CHECK (b > 0), EXCLUDE (b WITH <>))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertCount(2, $statement->exclusions);
        self::assertSame('e', $statement->exclusions[0]->name);
        self::assertSame('gist', $statement->exclusions[0]->method);
        self::assertSame(CheckingTime::DeferrableImmediate, $statement->exclusions[0]->checking);
        self::assertCount(1, $statement->definition->table->constraints);
        $expected = 'CREATE TABLE "public"."t"("a" integer, "b" integer, CHECK (("b" > 0)), CONSTRAINT "e" EXCLUDE USING "gist"("a" WITH =) WHERE (("a" > 0)) DEFERRABLE INITIALLY IMMEDIATE, EXCLUDE("b" WITH <>))';
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testExcludesRecognizesOnlyExcludeConstraints(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t (a INTEGER, CHECK (a > 0), EXCLUDE (a WITH =))');
        $constraints = Tree::outer($tree, ['TableConstraint']);
        self::assertFalse(Exclusions::excludes($constraints[0]));
        self::assertTrue(Exclusions::excludes($constraints[1]));
    }

    public function testBindDiagnosesContradictoryCheckingAttributes(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $binder->bind('CREATE TABLE t (a INTEGER, EXCLUDE (a WITH =) NOT DEFERRABLE INITIALLY DEFERRED)');
    }

    #[TestWith(['CREATE TABLE t(a INT, EXCLUDE USING gist (a WITH =) deferrable initially deferred)', CreateTableStatement::class, 'CREATE TABLE "public"."t"("a" integer, EXCLUDE USING "gist"("a" WITH =) DEFERRABLE INITIALLY DEFERRED)'])]
    public function testBindReadsLowercaseAttributes(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }
}
