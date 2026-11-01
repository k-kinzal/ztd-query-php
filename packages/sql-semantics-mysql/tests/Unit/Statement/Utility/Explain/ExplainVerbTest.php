<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainVerb;

#[CoversClass(ExplainVerb::class)]
#[Small]
final class ExplainVerbTest extends TestCase
{
    public function testCasesPreserveTheThreeCommandChoices(): void
    {
        self::assertSame(['EXPLAIN', 'DESCRIBE', 'DESC'], array_column(ExplainVerb::cases(), 'value'));
    }
}
