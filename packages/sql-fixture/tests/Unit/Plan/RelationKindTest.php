<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\RelationKind;
use SqlFixture\Plan\RelationSide;

#[CoversClass(RelationKind::class)]
#[UsesClass(RelationSide::class)]
final class RelationKindTest extends TestCase
{
    #[Test]
    public function eachCaseIsSpeltAsItsDbmlOperator(): void
    {
        self::assertSame('<', RelationKind::OneToMany->value);
        self::assertSame('>', RelationKind::ManyToOne->value);
        self::assertSame('-', RelationKind::OneToOne->value);
    }

    #[Test]
    public function manyToManyIsNotAnOperator(): void
    {
        $operators = array_map(
            static fn (RelationKind $kind): string => $kind->value,
            RelationKind::cases()
        );

        self::assertSame(['<', '>', '-'], $operators);
    }

    #[Test]
    public function oneToManyPutsTheParentOnTheLeft(): void
    {
        self::assertSame(RelationSide::Left, RelationKind::OneToMany->parentSide());
        self::assertSame(RelationSide::Right, RelationKind::OneToMany->childSide());
    }

    #[Test]
    public function manyToOnePutsTheParentOnTheRight(): void
    {
        self::assertSame(RelationSide::Right, RelationKind::ManyToOne->parentSide());
        self::assertSame(RelationSide::Left, RelationKind::ManyToOne->childSide());
    }

    #[Test]
    public function oneToOnePutsTheParentOnTheLeft(): void
    {
        self::assertSame(RelationSide::Left, RelationKind::OneToOne->parentSide());
        self::assertSame(RelationSide::Right, RelationKind::OneToOne->childSide());
    }

    #[Test]
    public function onlyOneToOneHoldsASingleChildRow(): void
    {
        self::assertTrue(RelationKind::OneToMany->childIsCollection());
        self::assertTrue(RelationKind::ManyToOne->childIsCollection());
        self::assertFalse(RelationKind::OneToOne->childIsCollection());
    }
}
