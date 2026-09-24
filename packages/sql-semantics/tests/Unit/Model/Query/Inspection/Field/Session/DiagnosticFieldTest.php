<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Session\DiagnosticField;
use SqlSemantics\Type\Nullability;

#[CoversClass(DiagnosticField::class)]
#[Medium]
final class DiagnosticFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['Level', 'Code', 'Message'], array_map(static fn (DiagnosticField $field): string => $field->label(), DiagnosticField::cases()));
    }

    #[TestWith([DiagnosticField::Code, 'integer'])]
    #[TestWith([DiagnosticField::Message, 'varchar'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(DiagnosticField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, DiagnosticField::cases()[0]->nullability());
    }
}
