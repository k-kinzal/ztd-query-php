<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonResponseKind;

#[CoversClass(JsonResponseKind::class)]
#[Small]
final class JsonResponseKindTest extends TestCase
{
    public function testCasesHoldTheKeywords(): void
    {
        self::assertSame(['ERROR', 'NULL', 'DEFAULT'], array_column(JsonResponseKind::cases(), 'value'));
    }
}
