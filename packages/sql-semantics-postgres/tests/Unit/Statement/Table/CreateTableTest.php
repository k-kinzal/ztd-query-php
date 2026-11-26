<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class)]
#[Medium]
final class CreateTableTest extends TestCase
{
    public function testCreatedSchemaIsTheWrittenSchema(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->inspect('CREATE TABLE s.t (a int)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        self::assertSame('s', $n1->createdSchema()?->value);
    }

    public function testDeriveStatementDeclaresTheTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $context = [];
        array_push($context, ...$semantics->inspect('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->inspect('CREATE TABLE n (LIKE t, id serial PRIMARY KEY, d date NOT NULL) INHERITS (t)', $context);
        self::assertSame([
          0 => 'a integer NotNull',
          1 => 'b integer Nullable',
          2 => 'c text Nullable',
          3 => 'id integer NotNull',
          4 => 'd date NotNull',
        ], array_map(static fn ($column): string => $column->name->value . ' ' . $column->type->name() . ' ' . $column->nullability->name, $statement->declarations()[0]->columns));
    }

    public function testDeriveElementDeclaresAnUnqualifiedTableInTheSchema(): void
    {
        $statement = (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('CREATE TABLE t (a int)');
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement::class, $statement->statement);
        $derivation = new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context);
        $statement->statement->deriveElement($derivation, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s'));
        self::assertSame('s', $derivation->facts()->declarations[0]->name->schema?->value);
    }

    public function testDeriveRelationAnswersTheNewTable(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->inspect('CREATE TABLE t (a int, b text)', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        self::assertSame(2, count($n1->deriveRelation(new \SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation($statement->context), new \SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment($statement->context))->shape->slots));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $statement = $semantics->inspect('CREATE UNLOGGED TABLE IF NOT EXISTS s.t (a int) PARTITION BY LIST (a) USING heap WITH (fillfactor = 70) TABLESPACE x', []);
        self::assertSame('CREATE UNLOGGED TABLE IF NOT EXISTS s.t (a INT) PARTITION BY list (a) USING heap WITH (fillfactor = 70) TABLESPACE x', $statement->toString());
    }
}
