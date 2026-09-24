<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Body\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\HandlerAction;

#[CoversClass(HandlerAction::class)]
final class HandlerActionTest extends TestCase
{
    public function testSpellsBothActions(): void
    {
        self::assertSame(['CONTINUE', 'EXIT'], array_column(HandlerAction::cases(), 'value'));
    }
}
