<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Option\OptionAction;

#[CoversClass(OptionAction::class)]
#[Small]
final class OptionActionTest extends TestCase
{
    public function testCasesSpellTheActions(): void
    {
        self::assertSame(['ADD', 'SET', 'DROP'], array_map(static fn (OptionAction $action): string => $action->value, OptionAction::cases()));
    }
}
