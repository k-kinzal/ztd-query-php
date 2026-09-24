<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Query\Inspection\Session\VariableScope;

#[CoversClass(VariableScope::class)]
final class VariableScopeTest extends TestCase
{
    public function testCasesAreSpelledAsTheirKeywords(): void
    {
        self::assertSame(['SESSION', 'GLOBAL'], array_column(VariableScope::cases(), 'value'));
    }
}
