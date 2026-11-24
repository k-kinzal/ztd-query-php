<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKey;

#[CoversClass(PrimaryKey::class)]
#[Small]
final class PrimaryKeyTest extends TestCase
{
    public function testToStringKeepsOrderedKeysIncludedRepetitionsAndTiming(): void
    {
        $a = new Identifier('a');
        $b = new Identifier('b');
        $key = new PrimaryKey([$b, $a], [$a, $a], new Identifier('my"key'), KeyTiming::InitiallyDeferred);

        self::assertSame('CONSTRAINT "my""key" PRIMARY KEY ("b", "a") INCLUDE ("a", "a") DEFERRABLE INITIALLY DEFERRED', $key->toString());
    }

    public function testToStringRejectsRepeatedKeyReferences(): void
    {
        $this->expectException(AnalysisException::class);

        new PrimaryKey([new Identifier('column'), new Identifier('column')]);
    }

    public function testToStringRejectsTooManyKeyAndIncludedAttributes(): void
    {
        $id = new Identifier('id');
        $this->expectException(AnalysisException::class);

        new PrimaryKey([$id], array_fill(0, 32, $id));
    }

    public function testToStringRetainsDetachedReferencesBothCallerArrays(): void
    {
        $field = new Identifier('id');
        $key = new PrimaryKey([&$field], [&$field]);
        $field = new Identifier('changed');

        self::assertSame('id', $key->keys[0]->value);
        self::assertSame('id', $key->included[0]->value);
    }
}
