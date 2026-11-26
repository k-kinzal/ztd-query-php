<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\UniqueKey;

#[CoversClass(UniqueKey::class)]
#[Small]
final class UniqueKeyTest extends TestCase
{
    public function testToStringRetainsNullEqualityAndAnOrderedDetachedPayload(): void
    {
        $key = new Identifier('key');
        $value = new Identifier('value');
        $unique = new UniqueKey([$key], [&$value, $key, &$value], new Identifier('label"'), KeyTiming::InitiallyImmediate, true);
        $value = new Identifier('changed');

        self::assertSame(
            'CONSTRAINT "label""" UNIQUE NULLS NOT DISTINCT ("key") INCLUDE ("value", "key", "value") DEFERRABLE INITIALLY IMMEDIATE',
            $unique->toString(),
        );
    }

    public function testToStringRejectsARepeatedKeyFieldBeforePublication(): void
    {
        $field = new Identifier('key');
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A unique key cannot repeat a key column.');

        new UniqueKey([$field, $field]);
    }
}
