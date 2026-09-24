<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Field\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Field\Session\DiagnosticCountField;
use SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection;
use SqlSemantics\Type\Nullability;

#[CoversClass(DiagnosticCountField::class)]
#[Medium]
final class DiagnosticCountFieldTest extends TestCase
{
    public function testLabelsFollowTheServerResultOrder(): void
    {
        self::assertSame(['@@session.error_count', '@@session.warning_count'], array_map(static fn (DiagnosticCountField $field): string => $field->label(), DiagnosticCountField::cases()));
    }

    #[TestWith([DiagnosticCountField::Errors, 'bigint'])]
    #[TestWith([DiagnosticCountField::Warnings, 'bigint'])]
    public function testTypeDeclaresTheBuiltinIdentityOfTheField(DiagnosticCountField $field, string $expected): void
    {
        self::assertSame($expected, $field->type());
    }

    #[TestWith([DiagnosticSelection::Warnings, DiagnosticCountField::Warnings])]
    #[TestWith([DiagnosticSelection::Errors, DiagnosticCountField::Errors])]
    public function testOfReturnsTheCounterOfTheSelectedConditions(DiagnosticSelection $selection, DiagnosticCountField $expected): void
    {
        self::assertSame($expected, DiagnosticCountField::of($selection));
    }

    public function testNullabilityDefaultsToPresent(): void
    {
        self::assertSame(Nullability::NotNull, DiagnosticCountField::cases()[0]->nullability());
    }
}
