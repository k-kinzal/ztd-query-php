<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Invocation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonEncoding;

#[CoversClass(JsonEncoding::class)]
#[Small]
final class JsonEncodingTest extends TestCase
{
    public function testNamedComparesWithoutCase(): void
    {
        self::assertSame([JsonEncoding::Utf32, null], [JsonEncoding::named('UtF32'), JsonEncoding::named('latin1')]);
    }
}
