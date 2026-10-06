<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ReferenceOption;

#[CoversClass(ReferenceOption::class)]
#[Small]
final class ReferenceOptionTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('NO ACTION', ReferenceOption::NoAction->value);
    }
}
