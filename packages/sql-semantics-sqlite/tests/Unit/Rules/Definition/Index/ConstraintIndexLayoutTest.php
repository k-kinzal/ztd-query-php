<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Index\ConstraintIndexLayout;

#[CoversClass(ConstraintIndexLayout::class)]
#[Small]
final class ConstraintIndexLayoutTest extends TestCase
{
    public function testKeepsThePrimaryStorageOrdinalInTheLayout(): void
    {
        $layout = new ConstraintIndexLayout(3, 2);

        self::assertSame(3, $layout->count);
        self::assertSame(2, $layout->primaryStorage);
        self::assertNull((new ConstraintIndexLayout(0))->primaryStorage);
    }
}
