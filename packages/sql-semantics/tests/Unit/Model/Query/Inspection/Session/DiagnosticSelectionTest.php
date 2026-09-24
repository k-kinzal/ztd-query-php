<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Session\DiagnosticSelection;

#[CoversClass(DiagnosticSelection::class)]
final class DiagnosticSelectionTest extends TestCase
{
    public function testCasesAreSpelledAsTheirKeywords(): void
    {
        self::assertSame(['WARNINGS', 'ERRORS'], array_column(DiagnosticSelection::cases(), 'value'));
    }
}
