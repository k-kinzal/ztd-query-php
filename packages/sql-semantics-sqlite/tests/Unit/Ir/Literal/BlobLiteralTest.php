<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Literal\BlobLiteral;

#[CoversClass(BlobLiteral::class)]
#[Small]
final class BlobLiteralTest extends TestCase
{
    public function testKeepsCompleteBytePairs(): void
    {
        self::assertSame('00FF', (new BlobLiteral('00FF'))->hex);
        self::assertSame('', (new BlobLiteral(''))->hex);
    }

    public function testRejectsHalfAByte(): void
    {
        $this->expectExceptionMessage('pairs of upper-case hexadecimal');

        new BlobLiteral('F');
    }
}
