<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\ConstraintTiming;
use SqlSemantics\Model\Statement\Configuration\SetAllConstraintsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConstraintTiming::class)]
#[Medium]
final class ConstraintTimingTest extends TestCase
{
    public function testRepresentsBothConstraintCheckingTimes(): void
    {
        self::assertSame(['IMMEDIATE', 'DEFERRED'], array_column(ConstraintTiming::cases(), 'value'));
    }

    public function testClassifiesTheRequestedTiming(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SET CONSTRAINTS ALL DEFERRED');
        self::assertInstanceOf(SetAllConstraintsStatement::class, $statement);
        self::assertSame(ConstraintTiming::Deferred, $statement->timing);
        self::assertSame('SET CONSTRAINTS ALL DEFERRED', $statement->toString());
    }
}
