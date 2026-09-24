<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\LoadLayout;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(LoadLayout::class)]
final class LoadLayoutTest extends TestCase
{
    public function testDefaultsToNoCharacterSetAndNoSkippedRows(): void
    {
        $layout = new LoadLayout();
        self::assertSame([null, 0], [$layout->characterSet, $layout->skippedRows]);
    }

    public function testRejectsANegativeRowCount(): void
    {
        $this->expectException(InvalidStructure::class);
        new LoadLayout('utf8mb4', skippedRows: -1);
    }
}
