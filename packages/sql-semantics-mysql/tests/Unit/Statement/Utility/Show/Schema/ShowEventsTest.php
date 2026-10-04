<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowEvents;

#[CoversClass(ShowEvents::class)]
#[Medium]
final class ShowEventsTest extends TestCase
{
    public function testDeriveStatementResolvesTheCondition(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW EVENTS WHERE Db = 1');
        self::assertInstanceOf(ShowEvents::class, $show->statement);
        self::assertSame([], $show->facts->diagnostics);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW EVENTS WHERE Db = 1');
        self::assertInstanceOf(ShowEvents::class, $show->statement);
        self::assertSame('Db', $show->facts->relation($show->statement)->shape->slots[0]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW EVENTS WHERE Db = 1', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('SHOW EVENTS WHERE Db = 1')->toString());
    }
}
