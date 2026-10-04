<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Subquery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;

#[CoversClass(Quantifier::class)]
#[Small]
final class QuantifierTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['ALL', 'ANY'], array_column(Quantifier::cases(), 'value'));
    }
}
