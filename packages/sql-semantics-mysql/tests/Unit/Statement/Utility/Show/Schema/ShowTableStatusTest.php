<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTableStatus;

#[CoversClass(ShowTableStatus::class)]
#[Medium]
final class ShowTableStatusTest extends TestCase
{
    public function testDeriveStatementResolvesTheCondition(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW TABLE STATUS IN db WHERE `Rows` > 1');
        self::assertInstanceOf(ShowTableStatus::class, $show);
        self::assertSame([], $show->diagnostics());
        self::assertSame('Name', $show->field(0)->name?->value);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW TABLE STATUS IN db WHERE `Rows` > 1');
        self::assertInstanceOf(ShowTableStatus::class, $show);
        self::assertSame('Engine', $show->relationInformation()->shape->slots[1]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW TABLE STATUS IN db WHERE `Rows` > 1', (new Semantics(Dialect::MySql))->analyze('SHOW TABLE STATUS IN db WHERE `Rows` > 1')->toString());
    }
}
