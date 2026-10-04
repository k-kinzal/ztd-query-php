<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Cast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\TransformDirection::class)]
#[Small]
final class TransformDirectionTest extends TestCase
{
    public function testToSqlIsTwoKeywords(): void
    {
        self::assertSame('TO SQL', \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Cast\TransformDirection::ToSql->value);
    }
}
