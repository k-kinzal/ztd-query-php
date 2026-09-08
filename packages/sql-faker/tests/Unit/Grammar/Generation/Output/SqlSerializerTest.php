<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;

#[CoversClass(SqlSerializer::class)]

final class SqlSerializerTest extends TestCase
{
    public function testSerializeConcatenatesExactlyWithoutTrimmingOrNormalizing(): void
    {
        $serializer = new SqlSerializer();
        self::assertSame(' NOW () ', $serializer->serialize([' ', 'NOW', ' ', '(', ')', ' ']));
        self::assertSame('', $serializer->serialize([]));
    }
}
