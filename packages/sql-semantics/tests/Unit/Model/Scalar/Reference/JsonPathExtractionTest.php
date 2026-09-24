<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\Reference\JsonPathExtraction;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonPathExtraction::class)]
#[Medium]
final class JsonPathExtractionTest extends TestCase
{
    #[TestWith(['mysql-5.7.44', '->', 'json', false])]
    #[TestWith(['mysql-8.0.44', '->>', 'longtext', true])]
    #[TestWith(['mysql-8.4.7', '->', 'json', false])]
    #[TestWith(['mysql-9.1.0', '->>', 'longtext', true])]
    public function testInputsRetainTheColumnAndThePathLiteral(string $version, string $operator, string $type, bool $unquoted): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (doc JSON NOT NULL)'));
        $query = $binder->bind('SELECT t.doc ' . $operator . " '$.name' FROM t");
        self::assertInstanceOf(BoundSelect::class, $query);
        $path = $query->outputs[0]->expression;
        self::assertInstanceOf(JsonPathExtraction::class, $path);
        self::assertSame('doc', $path->column->columnBinding()?->column->name);
        self::assertSame("'$.name'", $path->path->text);
        self::assertSame($unquoted, $path->unquoted);
        self::assertSame([$path->column, $path->path], $path->inputs());
        self::assertSame($type, $path->type->name);
        self::assertSame(Nullability::MaybeNull, $path->nullability);
        self::assertSame(ExpressionKind::JsonPath, $path->kind);
        self::assertSame('SELECT (`t`.`doc` ' . $operator . " '$.name') FROM `t`", $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testInputsRejectAnOperandThatIsNotAColumn(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $this->expectException(InvalidStructure::class);
        new JsonPathExtraction($path->source, $path, $path, false);
    }

    public function testInputsAcceptAResultColumnOfAnInspectionFilter(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.1.0'))->build());
        $statement = $binder->bind("SHOW EVENTS WHERE name ->> '$.a' = 1");
        self::assertSame("SHOW EVENTS WHERE ((`Name` ->> '$.a') = 1)", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testInputsRejectAPathThatIsNotAString(): void
    {
        $path = Expression::literal(1, Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $this->expectException(InvalidStructure::class);
        new JsonPathExtraction($path->source, Expression::reference(['doc'], Dialect::MySql), $path, false);
    }

    public function testInputsRejectAnotherDialect(): void
    {
        $path = Expression::literal('$.a', Dialect::PostgreSql);
        self::assertInstanceOf(Literal::class, $path);
        $this->expectException(InvalidStructure::class);
        new JsonPathExtraction($path->source, Expression::reference(['doc'], Dialect::PostgreSql), $path, false);
    }

    #[TestWith([false, '->'])]
    #[TestWith([true, '->>'])]
    public function testSpellingIsTheInlineOperator(bool $unquoted, string $operator): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        self::assertSame($operator, (new JsonPathExtraction($path->source, Expression::reference(['doc'], Dialect::MySql), $path, $unquoted))->spelling());
    }

    public function testWithFactsPreservesTheOperands(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $extraction = new JsonPathExtraction($path->source, Expression::reference(['doc'], Dialect::MySql), $path, true);
        $copy = $extraction->withFacts($extraction->facts);
        self::assertNotSame($extraction, $copy);
        self::assertSame($path, $copy->path);
        self::assertTrue($copy->unquoted);
    }

    public function testWithFactsRejectsContradictoryFacts(): void
    {
        $path = Expression::literal('$.a', Dialect::MySql);
        self::assertInstanceOf(Literal::class, $path);
        $extraction = new JsonPathExtraction($path->source, Expression::reference(['doc'], Dialect::MySql), $path, true);
        $this->expectException(InvalidStructure::class);
        $extraction->withFacts($path->facts);
    }
}
