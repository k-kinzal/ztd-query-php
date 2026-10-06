<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowColumns;

#[CoversClass(ShowColumns::class)]
#[Medium]
final class ShowColumnsTest extends TestCase
{
    public function testDeriveStatementResolvesTheTable(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW COLUMNS FROM t');
        self::assertInstanceOf(ShowColumns::class, $show->statement);
        self::assertSame(6, count($show->fields() ?? []));
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\UndeclaredTable::class, $show->facts->relation($show->statement->table)->table);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW COLUMNS FROM t');
        self::assertInstanceOf(ShowColumns::class, $show->statement);
        self::assertSame('Field', $show->facts->relation($show->statement)->shape->slots[0]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW COLUMNS FROM t', (new Semantics(Dialect::MySql))->analyze('SHOW COLUMNS FROM t')->toString());
    }
}
