<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\HandlerRule;

#[CoversClass(HandlerRule::class)]
#[Medium]
final class HandlerRuleTest extends TestCase
{
    public function testStatementLowersOpenAndClose(): void
    {
        self::assertSame('HANDLER t OPEN AS h', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('handler t open as h')->toString());
        self::assertSame('HANDLER h CLOSE', (new Semantics(Dialect::MySql))->analyze('handler h close')->toString());
    }

    public function testReadLowersScans(): void
    {
        self::assertSame('HANDLER h READ FIRST WHERE a = 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('handler h read first where a = 1')->toString());
        self::assertSame('HANDLER h READ NEXT LIMIT 1', (new Semantics(Dialect::MySql))->analyze('handler h read next limit 1')->toString());
    }

    public function testIndexLowersReadsAndSeeks(): void
    {
        self::assertSame('HANDLER h READ i LAST', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('handler h read i last')->toString());
        self::assertSame('HANDLER h READ i <= (1, 2)', (new Semantics(Dialect::MySql))->analyze('handler h read i <= (1, 2)')->toString());
        self::assertSame('HANDLER h READ i > (1)', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('handler h read i > (1)')->toString());
    }
    public function testReadRetainsTheIndexAndCompletePredicateOnRefusal(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('HANDLER h READ i LAST WHERE ROW(1,2)+1 LIMIT 2');
            self::fail('A non-scalar handler predicate cannot be an ordinary statement.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead::class, $failure->problem);
            self::assertSame('h', $failure->problem->handler->name->value);
            self::assertSame('i', $failure->problem->index?->value);
            self::assertSame(\SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection::Last, $failure->problem->mode);
            self::assertNotNull($failure->problem->where);
            self::assertNotNull($failure->problem->limit);
        }
    }

    public function testIndexRetainsAllSeekOperandsBeforeRefusal(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('HANDLER h READ i > (ROW(1,2), DEFAULT) WHERE a');
            self::fail('A non-scalar handler key cannot be an ordinary statement.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Handler\RefusedRead::class, $failure->problem);
            self::assertCount(2, $failure->problem->values);
            self::assertSame(\SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison::Greater, $failure->problem->mode);
            self::assertNotNull($failure->problem->where);
        }
    }

    public function testFunctionsRefusesStoredCallsBeforePublishingReads(): void
    {
        foreach (['HANDLER h READ FIRST WHERE ABS(d.f())', 'HANDLER h READ i FIRST WHERE f()', 'HANDLER h READ i = (f())'] as $sql) {
            try {
                (new Semantics(Dialect::MySql))->analyze($sql);
                self::fail('A stored-function handler read cannot be published.');
            } catch (\SqlSemantics\Construction\RejectedInput $failure) {
                self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet::class, $failure->problem);
                self::assertSame('stored functions in HANDLER ... READ', $failure->problem->feature);
                self::assertFalse($failure->aborts);
            }
        }
    }
}
