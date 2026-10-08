<?php

declare(strict_types=1);

namespace Tests\Unit\Value\Json;

use MySqlMemory\Value\Json\JsonSyntax;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonSyntax::class)]
#[Small]
final class JsonSyntaxTest extends TestCase
{
    public function testReasonIsTheMessageAndThePositionIsTheCode(): void
    {
        $failure = new JsonSyntax('Invalid value.', 3);

        self::assertSame(['Invalid value.', 3, false, 'Invalid value.', 3], [$failure->reason, $failure->position, $failure->deep, $failure->getMessage(), $failure->getCode()]);
    }
}
