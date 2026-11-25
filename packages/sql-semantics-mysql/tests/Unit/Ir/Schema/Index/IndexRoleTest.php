<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexRole;

#[CoversClass(IndexRole::class)]
#[Small]
final class IndexRoleTest extends TestCase
{
    public function testNativePoliciesHaveDistinctValues(): void
    {
        self::assertSame(['primary', 'unique', 'ordinary'], array_column(IndexRole::cases(), 'value'));
    }
}
