<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Type;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Type\UnknownReason::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class UnknownReasonTest extends TestCase
{
    public function testReasonsDescribeMissingInputsRatherThanImplementationGaps(): void
    {
        self::assertSame(['catalog-not-supplied', 'parameter-not-supplied', 'null-literal'], array_column(\SqlSemantics\Semantic\Type\UnknownReason::cases(), 'value'));
    }
}
