<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyDirection::class)]
#[Small]
final class CopyDirectionTest extends TestCase
{
    public function testValueIsTheKeyword(): void
    {
        self::assertSame('TO', \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyDirection::To->value);
    }
}
