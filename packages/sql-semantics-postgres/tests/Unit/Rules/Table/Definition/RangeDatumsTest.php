<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\RangeDatums::class)]
#[Medium]
final class RangeDatumsTest extends TestCase
{
    public function testCheckedRefusesAnEmptySide(): void
    {
        $this->expectExceptionMessage('A range bound side is an ordered list of at least one datum.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\RangeDatums())->checked([]);
    }
}
