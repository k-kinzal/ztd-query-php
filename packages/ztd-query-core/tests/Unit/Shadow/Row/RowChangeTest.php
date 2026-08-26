<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Row;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Shadow\Row\RowChange;

#[CoversClass(RowChange::class)]
final class RowChangeTest extends TestCase
{
    public function testKeepsBothSidesOfTheChange(): void
    {
        $change = new RowChange(['id' => 1, 'total' => 100], ['id' => 1, 'total' => 200]);

        self::assertSame(['id' => 1, 'total' => 100], $change->before);
        self::assertSame(['id' => 1, 'total' => 200], $change->after);
    }
}
