<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\IntrinsicExpressions;

#[CoversClass(IntrinsicExpressions::class)]
#[Medium]
final class IntrinsicExpressionsTest extends TestCase
{
    public function testWriteRoutesAnIntervalOperationAndLeavesLiteralSerializationToItsOwner(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT CURRENT_DATE + INTERVAL 2 DAY, 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertNotNull(IntrinsicExpressions::write($query->outputs[0]->expression));
        self::assertNull(IntrinsicExpressions::write($query->outputs[1]->expression));
        self::assertStringContainsString('DATE_ADD(', $query->toString());
    }
}
