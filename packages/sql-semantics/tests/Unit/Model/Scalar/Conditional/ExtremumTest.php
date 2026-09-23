<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Conditional\Extremum;
use SqlSemantics\Model\Scalar\Conditional\ExtremumKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Extremum::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ExtremumTest extends TestCase
{
    #[TestWith(['SELECT GREATEST(1,2.5)', ExtremumKind::Greatest, 'SELECT GREATEST(1, 2.5)'])]
    #[TestWith(['SELECT LEAST(1,2.5)', ExtremumKind::Least, 'SELECT LEAST(1, 2.5)'])]
    public function testInputsRetainsConvertedCandidates(string $sql, ExtremumKind $selection, string $serialized): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(Extremum::class, $value);
        self::assertSame($selection, $value->selection);
        self::assertSame($value->arguments, $value->inputs());
        self::assertSame('numeric', $value->arguments[0]->type->name);
        self::assertSame('numeric', $value->arguments[1]->type->name);
        self::assertSame($serialized, $statement->toString());
        self::assertSame($serialized, $binder->bind($serialized)->toString());
    }

    public function testInputsRequiresAtLeastOneCandidate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT GREATEST(1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(Extremum::class, $value);
        $this->expectException(InvalidStructure::class);
        new Extremum($value->facts, $value->source, $value->selection, []);
    }

    public function testWithFactsKeepsTheSelectedDirectionAndCandidates(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT LEAST(NULL,1)');
        self::assertInstanceOf(BoundSelect::class, $statement);
        $value = $statement->outputs[0]->expression;
        self::assertInstanceOf(Extremum::class, $value);
        $copy = $value->withFacts($value->facts);
        self::assertNotSame($copy, $value);
        self::assertSame(Nullability::NotNull, $copy->nullability);
        self::assertSame($value->arguments, $copy->arguments);
        self::assertSame('LEAST', $copy->spelling());
    }
}
