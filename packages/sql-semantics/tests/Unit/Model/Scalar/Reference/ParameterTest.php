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
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\Parameter;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Parameter::class)]
#[Medium]
final class ParameterTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT $1', '$1'])]
    #[TestWith([Dialect::MySql, 'SELECT ?', '?'])]
    #[TestWith([Dialect::Sqlite, 'SELECT :name', ':name'])]
    public function testInputsHasNoOperandsForABindingKey(Dialect $dialect, string $sql, string $name): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $parameter = $statement->outputs[0]->expression;
        self::assertInstanceOf(Parameter::class, $parameter);
        self::assertSame($name, $parameter->name);
        self::assertSame([], $parameter->inputs());
        self::assertSame('unknown', $parameter->type->name);
        self::assertSame(Nullability::Unknown, $parameter->nullability);
        self::assertSame($sql, $statement->toString());
        self::assertSame($sql, $binder->bind($sql)->toString());
    }

    public function testSpellingReturnsTheBindingKey(): void
    {
        $origin = Expression::reference(['x'], Dialect::PostgreSql);
        $parameter = new Parameter($origin->facts, $origin->source, '$2');
        self::assertSame('$2', $parameter->spelling());
        self::assertSame('$2', $parameter->structure()->toString());
    }

    #[TestWith(['x'])]
    #[TestWith(['$1 + 1'])]
    #[TestWith([''])]
    #[TestWith(['?'])]
    public function testRejectsAnythingButOneBindingKeyOfTheDialect(string $name): void
    {
        $origin = Expression::reference(['x'], Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new Parameter($origin->facts, $origin->source, $name);
    }

    public function testWithFactsKeepsTheBindingKey(): void
    {
        $origin = Expression::reference(['x'], Dialect::PostgreSql);
        $parameter = new Parameter($origin->facts, $origin->source, '$1');
        $copy = $parameter->withFacts(new ExpressionFacts($parameter->type, Nullability::MaybeNull));
        self::assertNotSame($parameter, $copy);
        self::assertSame('$1', $copy->name);
        self::assertSame(Nullability::MaybeNull, $copy->nullability);
        self::assertSame(Nullability::Unknown, $parameter->nullability);
    }
}
