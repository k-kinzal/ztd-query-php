<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\Functions;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole;

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

    public function testNamesOfAnExpressionDefaultKeepOnlyStateAndWaitingFunctions(): void
    {
        $functions = (new Functions())->names(ExpressionRole::Default);
        self::assertSame('row_count', $functions['row_count']);
        self::assertSame('source_pos_wait', $functions['master_pos_wait']);
        self::assertArrayNotHasKey('now', $functions);
        self::assertArrayNotHasKey('rand', $functions);
    }
}
