<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Replication\LogEventField;
use SqlSemantics\Type\Nullability;

#[CoversClass(LogEventField::class)]
#[Medium]
final class LogEventFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Log_name', 'Pos', 'Event_type', 'Server_id', 'End_log_pos', 'Info'], array_map(static fn (LogEventField $field): string => $field->label(), LogEventField::cases()));
    }

    #[TestWith([LogEventField::Position, 'bigint'])]
    #[TestWith([LogEventField::Info, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(LogEventField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, LogEventField::cases()[0]->nullability());
    }
}
