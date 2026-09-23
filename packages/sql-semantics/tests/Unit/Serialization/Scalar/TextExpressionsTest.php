<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\TextExpressions;

#[CoversClass(TextExpressions::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TextExpressionsTest extends TestCase
{
    public function testWritePreservesOperandRoles(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT POSITION('a' IN 'cat')");
        self::assertSame("SELECT POSITION('a' IN 'cat')", $query->toString());
        self::assertSame("SELECT POSITION('a' IN 'cat')", $binder->bind($query->toString())->toString());
    }
}
