<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\View\ViewAlgorithm;

#[CoversClass(ViewAlgorithm::class)]
#[Small]
final class ViewAlgorithmTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('MERGE', ViewAlgorithm::Merge->value);
    }
}
