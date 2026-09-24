<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\CopyErrorAction;

#[CoversClass(CopyErrorAction::class)]
final class CopyErrorActionTest extends TestCase
{
    public function testCasesUseTheServerArguments(): void
    {
        self::assertSame(['stop', 'ignore'], array_map(static fn (CopyErrorAction $case): string => $case->value, CopyErrorAction::cases()));
    }
}
