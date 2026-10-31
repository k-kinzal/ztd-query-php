<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectItem;
use SqlSemantics\Statement\Node;

#[CoversNothing]
#[Medium]
final class SelectItemTest extends TestCase
{
    public function testASelectItemIsARenderableNode(): void
    {
        self::assertTrue(interface_exists(SelectItem::class));
        self::assertContains(Node::class, class_implements(SelectItem::class));
    }

    public function testAProjectedExpressionIsASelectItem(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT a, 1 AS one FROM t');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation);
        self::assertInstanceOf(Select::class, $operation->query);
        self::assertContainsOnlyInstancesOf(SelectItem::class, $operation->query->items);
        self::assertContainsOnlyInstancesOf(SelectExpression::class, $operation->query->items);
        self::assertCount(2, $operation->query->items);
    }
}
