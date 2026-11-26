<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Ordering\AliasHeading;

#[CoversClass(AliasHeading::class)]
#[Small]
final class AliasHeadingTest extends TestCase
{
    public function testNormalizePreservesGraphicUtf8AndTrailingSpaces(): void
    {
        self::assertSame(' value  ', (new AliasHeading())->normalize("\t\x7f value  "));
    }

}
