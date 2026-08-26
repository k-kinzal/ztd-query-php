<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\ReadOnlySetException;

#[CoversClass(ReadOnlySetException::class)]
final class ReadOnlySetExceptionTest extends TestCase
{
    #[Test]
    public function testCannotWriteNamesTheTableTheCallerTriedToChange(): void
    {
        self::assertSame(
            'Cannot set "order" on a generated fixture set: it is what one generation produced.',
            ReadOnlySetException::cannotWrite('order')->getMessage()
        );
    }

    #[Test]
    public function testCannotWriteNamesAPositionTheSameWayAsATable(): void
    {
        self::assertStringContainsString('"0"', ReadOnlySetException::cannotWrite(0)->getMessage());
    }

    #[Test]
    public function testCannotRemoveNamesTheTableTheCallerTriedToRemove(): void
    {
        self::assertSame(
            'Cannot remove "order" from a generated fixture set: it is what one generation produced.',
            ReadOnlySetException::cannotRemove('order')->getMessage()
        );
    }

    #[Test]
    public function testCannotRemoveNamesAPositionTheSameWayAsATable(): void
    {
        self::assertStringContainsString('"0"', ReadOnlySetException::cannotRemove(0)->getMessage());
    }
}
