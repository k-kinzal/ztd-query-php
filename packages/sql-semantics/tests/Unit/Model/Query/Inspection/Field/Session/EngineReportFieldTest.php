<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Session\EngineReportField;
use SqlSemantics\Type\Nullability;

#[CoversClass(EngineReportField::class)]
#[Medium]
final class EngineReportFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Type', 'Name', 'Status'], array_map(static fn (EngineReportField $field): string => $field->label(), EngineReportField::cases()));
    }

    public function testTypeDefaultsToTextForEveryField(): void
    {
        self::assertSame(array_fill(0, count(EngineReportField::cases()), 'varchar'), array_map(static fn (EngineReportField $field): string => $field->type(), EngineReportField::cases()));
    }

    public function testNullabilityDefaultsToPresentForEveryField(): void
    {
        self::assertSame(array_fill(0, count(EngineReportField::cases()), Nullability::NotNull), array_map(static fn (EngineReportField $field): Nullability => $field->nullability(), EngineReportField::cases()));
    }
}
