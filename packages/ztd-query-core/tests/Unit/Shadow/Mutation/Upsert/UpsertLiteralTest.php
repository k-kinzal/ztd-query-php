<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Upsert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertLiteral;

#[CoversClass(UpsertLiteral::class)]
final class UpsertLiteralTest extends TestCase
{
    public function testValuePreservesObjectIdentity(): void
    {
        $value = new stdClass();

        self::assertSame($value, (new UpsertLiteral($value))->value());
    }

    public function testValuePreservesNestedArrayKeysAndValues(): void
    {
        $value = ['json' => [3 => ['active' => true, 'missing' => null]]];

        self::assertSame($value, (new UpsertLiteral($value))->value());
    }
}
