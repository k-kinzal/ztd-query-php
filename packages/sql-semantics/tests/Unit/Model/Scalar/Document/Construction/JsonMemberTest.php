<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Document\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Document\Construction\JsonMember;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\Input;

#[CoversClass(JsonMember::class)]
final class JsonMemberTest extends TestCase
{
    public function testKeepsTheKeyAndTheFormattedValue(): void
    {
        $key = Expression::literal('a', Dialect::PostgreSql);
        $value = new Input(Expression::literal('{}', Dialect::PostgreSql), Format::Json);
        $member = new JsonMember($key, $value);
        self::assertSame($key, $member->key);
        self::assertSame($value, $member->value);
    }
}
