<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Function\WindowCall;
use SqlSemantics\Model\Window\NamedWindow;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NamedWindow::class)]
#[Medium]
final class NamedWindowTest extends TestCase
{
    public function testRefersToADefinitionWithoutLocalExpressions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)'));
        $query = $binder->bind('SELECT sum(id) OVER w FROM t WINDOW w AS (PARTITION BY x)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $call = $query->outputs[0]->expression;
        self::assertInstanceOf(WindowCall::class, $call);
        $window = $call->window;
        self::assertInstanceOf(NamedWindow::class, $window);
        self::assertSame('w', $window->name);
        self::assertSame($query->windows[0]->name, $window->name);
        self::assertSame([], $window->expressions());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }

    public function testExpressionsAreEmptyForAReferenceByName(): void
    {
        $window = new NamedWindow('w');
        self::assertSame('w', $window->name);
        self::assertSame([], $window->expressions());
    }
}
