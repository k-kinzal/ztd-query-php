<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;

#[CoversClass(ShowTables::class)]
#[Medium]
final class ShowTablesTest extends TestCase
{
    public function testDeriveStatementLeavesTheShapeOpenWithoutADatabase(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW EXTENDED FULL TABLES');
        self::assertInstanceOf(ShowTables::class, $show->statement);
        self::assertFalse($show->shape()?->complete());
    }

    public function testDeriveRelationNamesTheColumnAfterTheContextDatabase(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW EXTENDED FULL TABLES');
        self::assertInstanceOf(ShowTables::class, $show->statement);
        $named = (new Semantics(Dialect::MySql))->analyze('SHOW FULL TABLES', new \SqlSemantics\Contract\AnalysisContext($show->profile(), [new \SqlSemantics\Statement\Identifier\Name('shop')]));
        self::assertSame(['Tables_in_shop', 'Table_type'], [$named->field(0)->name?->value, $named->field(1)->name?->value]);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW EXTENDED FULL TABLES', (new Semantics(Dialect::MySql))->analyze('SHOW EXTENDED FULL TABLES')->toString());
    }
}
