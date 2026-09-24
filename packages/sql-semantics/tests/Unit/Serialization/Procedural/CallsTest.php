<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Procedural;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Procedural\CallStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Procedural\Calls;

#[CoversClass(Calls::class)]
#[Medium]
final class CallsTest extends TestCase
{
    public function testWriteQuotesTheNameAndSpellsTheArgumentList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CALL `a``b`');
        self::assertInstanceOf(CallStatement::class, $statement);
        self::assertSame('CALL `a``b`()', Calls::write($statement)->toString());
    }
}
