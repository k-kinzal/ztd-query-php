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
use SqlSemantics\Model\Window\Window;
use SqlSemantics\Model\Window\WindowSpecification;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Window::class)]
#[Medium]
final class WindowTest extends TestCase
{
    public function testExpressionsBelongToInlineSpecificationsAndNotToReferences(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER NOT NULL, x INTEGER)'));
        $query = $binder->bind('SELECT sum(id) OVER (PARTITION BY x ORDER BY id), sum(id) OVER w FROM t WINDOW w AS (PARTITION BY x)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $inline = $query->outputs[0]->expression;
        $named = $query->outputs[1]->expression;
        self::assertInstanceOf(WindowCall::class, $inline);
        self::assertInstanceOf(WindowCall::class, $named);
        self::assertInstanceOf(WindowSpecification::class, $inline->window);
        self::assertInstanceOf(NamedWindow::class, $named->window);
        self::assertSame(['x', 'id'], array_map(static fn ($expression): ?string => $expression->columnBinding()?->column->name, $inline->window->expressions()));
        self::assertSame([], $named->window->expressions());
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }
}
