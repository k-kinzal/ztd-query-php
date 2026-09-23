<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Foreign\ServerVersionChange;

#[CoversClass(ServerVersionChange::class)]
#[Medium]
final class ServerVersionChangeTest extends TestCase
{
    public function testCasesSeparateUnchangedAndRemovedMetadata(): void
    {
        self::assertSame([ServerVersionChange::Keep, ServerVersionChange::Remove], ServerVersionChange::cases());
    }
}
