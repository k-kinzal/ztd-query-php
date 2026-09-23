<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Conditional\JsonMembership;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(JsonMembership::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class JsonMembershipTest extends TestCase
{
    #[TestWith(["SELECT 1 MEMBER OF ('[1,2]')"]) ]
    #[TestWith(["SELECT 1 MEMBER ('[1,2]')"]) ]
    public function testInputsRetainsTheValueAndJsonArray(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        $member = $statement->outputs[0]->expression;
        self::assertInstanceOf(JsonMembership::class, $member);
        self::assertSame('1', $member->value->spelling());
        self::assertSame("'[1,2]'", $member->array->spelling());
        self::assertSame([$member->value, $member->array], $member->inputs());
        self::assertSame(Nullability::NotNull, $member->nullability);
        self::assertSame('SELECT (1 MEMBER OF(\'[1,2]\'))', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testSpellingIsIndependentOfTheOptionalOfKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT NULL MEMBER ('[]')");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $member = $statement->outputs[0]->expression;
        self::assertInstanceOf(JsonMembership::class, $member);
        self::assertSame('MEMBER OF', $member->spelling());
        self::assertSame(Nullability::AlwaysNull, $member->nullability);
    }

    public function testWithFactsPreservesTheOperandRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT 1 MEMBER OF ('[1]')");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $member = $statement->outputs[0]->expression;
        self::assertInstanceOf(JsonMembership::class, $member);
        $copy = $member->withFacts($member->facts);
        self::assertSame($member->value, $copy->value);
        self::assertSame($member->array, $copy->array);
    }
}
