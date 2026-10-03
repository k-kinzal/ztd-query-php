<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;

#[CoversClass(ConflictAction::class)]
#[Small]
final class ConflictActionTest extends TestCase
{
    public function testCasesDistinguishTheDeclaredChoices(): void
    {
        self::assertSame(['', 'ROLLBACK', 'ABORT', 'FAIL', 'IGNORE', 'REPLACE'], array_map(static fn (ConflictAction $choice): string => $choice->value, ConflictAction::cases()));
    }
}
