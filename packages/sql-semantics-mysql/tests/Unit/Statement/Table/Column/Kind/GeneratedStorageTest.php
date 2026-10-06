<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\GeneratedStorage;

#[CoversClass(GeneratedStorage::class)]
#[Small]
final class GeneratedStorageTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame('VIRTUAL', GeneratedStorage::Virtual->value);
    }
}
