<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium;

#[CoversClass(StorageMedium::class)]
#[Small]
final class StorageMediumTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('MEMORY', StorageMedium::Memory->value);
    }
}
