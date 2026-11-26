<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexAlgorithm;

#[CoversClass(IndexAlgorithm::class)]
#[Small]
final class IndexAlgorithmTest extends TestCase
{
    public function testNativeNamesAreDistinct(): void
    {
        self::assertSame(['DEFAULT', 'INPLACE', 'COPY'], array_column(IndexAlgorithm::cases(), 'value'));
    }
}
