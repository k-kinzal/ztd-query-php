<?php

declare(strict_types=1);

namespace Tests\Unit\Variable;

use MySqlMemory\Variable\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Scope::class)]
#[Small]
final class ScopeTest extends TestCase
{
    public function testCasesNameWhereAVariableLives(): void
    {
        self::assertSame(['GLOBAL', 'SESSION', 'BOTH'], array_map(static fn (Scope $scope): string => $scope->value, Scope::cases()));
    }

    public function testFromReadsTheWrittenScope(): void
    {
        self::assertSame([Scope::Global, Scope::Session, Scope::Both, null], [Scope::from('GLOBAL'), Scope::from('SESSION'), Scope::from('BOTH'), Scope::tryFrom('PERSIST')]);
    }
}
