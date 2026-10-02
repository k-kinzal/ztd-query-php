<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ExplainRows;
use SqlSemantics\Platform\Sqlite\Statement\Inspection\ExplainMode;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Shape\UniqueField;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ExplainRows::class)]
#[Small]
final class ExplainRowsTest extends TestCase
{
    public function testFactListsTheEightColumnsOfAProgramReport(): void
    {
        $fact = (new ExplainRows())->fact(ExplainMode::Program, Comparison::AsciiInsensitive);
        $names = array_map(static fn (object $slot): ?string => $slot->name?->value, $fact->shape->slots);

        self::assertSame(['addr', 'opcode', 'p1', 'p2', 'p3', 'p4', 'p5', 'comment'], $names);
        self::assertTrue($fact->shape->complete());
        self::assertEquals(new Known(Storage::Integer), $fact->shape->slots[0]->type);
        self::assertEquals(new Known(Storage::Text), $fact->shape->slots[1]->type);
        self::assertSame(Nullability::NotNull, $fact->shape->slots[1]->nullability);
        self::assertSame(Nullability::Nullable, $fact->shape->slots[7]->nullability);
    }

    public function testFactListsTheFourColumnsOfAQueryPlanReport(): void
    {
        $fact = (new ExplainRows())->fact(ExplainMode::QueryPlan, Comparison::AsciiInsensitive);
        $names = array_map(static fn (object $slot): ?string => $slot->name?->value, $fact->shape->slots);

        self::assertSame(['id', 'parent', 'notused', 'detail'], $names);
        self::assertInstanceOf(UniqueField::class, $fact->lookup('DETAIL'));
        self::assertEquals(new Known(Storage::Text), $fact->shape->slots[3]->type);
    }
}
