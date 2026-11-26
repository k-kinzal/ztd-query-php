<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;

#[CoversClass(TextCollation::class)]
#[Small]
final class TextCollationTest extends TestCase
{
    public function testCNamesTheStockByteCollation(): void
    {
        self::assertSame('C', TextCollation::C->value);
    }
}
