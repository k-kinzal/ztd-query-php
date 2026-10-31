<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Functions;

#[CoversClass(Functions::class)]
#[Small]
final class FunctionsTest extends TestCase
{
    public function testNamesKeepsAliasesAndDiagnosticNames(): void
    {
        $functions = (new Functions())->names();
        self::assertSame('user', $functions['session_user']);
        self::assertSame('database', $functions['schema']);
        self::assertSame('version()', $functions['version']);
        self::assertArrayNotHasKey('abs', $functions);
    }
}
