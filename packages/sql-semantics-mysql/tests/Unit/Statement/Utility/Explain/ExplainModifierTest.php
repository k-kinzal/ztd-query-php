<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier;

#[CoversClass(ExplainModifier::class)]
#[Small]
final class ExplainModifierTest extends TestCase
{
    public function testCasesSpellTheKeywords(): void
    {
        self::assertSame(['EXTENDED', 'PARTITIONS'], array_column(ExplainModifier::cases(), 'value'));
    }
}
