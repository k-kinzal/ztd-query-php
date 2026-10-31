<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

#[CoversClass(VariableScope::class)]
#[Small]
final class VariableScopeTest extends TestCase
{
    public function testSessionRecognizesBothWrittenSessionScopes(): void
    {
        self::assertSame([false, true, true, false, false], array_map(static fn (VariableScope $scope): bool => $scope->session(), VariableScope::cases()));
    }

    public function testCasesSpellEveryScope(): void
    {
        self::assertSame(['GLOBAL', 'SESSION', 'LOCAL', 'PERSIST', 'PERSIST_ONLY'], array_column(VariableScope::cases(), 'value'));
    }
}
