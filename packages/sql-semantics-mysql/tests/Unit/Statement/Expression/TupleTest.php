<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Tuple;

#[CoversClass(Tuple::class)]
#[Small]
final class TupleTest extends TestCase
{
    public function testNameIsTheRowResultType(): void
    {
        self::assertSame('ROW', (new Tuple(2))->name());
    }

    public function testAWidthBelowTwoIsRejected(): void
    {
        $this->expectExceptionMessage('A row has at least two columns.');

        new Tuple(1);
    }
}
