<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\ListRule;

#[CoversClass(ListRule::class)]
#[Medium]
final class ListRuleTest extends TestCase
{
    public function testItemsFlattensTheList(): void
    {
        self::assertSame('CALL p(1, 2, 3)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('call p(1, 2, 3)')->toString());
        self::assertSame('DELETE QUICK IGNORE QUICK FROM t', (new Semantics(Dialect::MySql))->analyze('delete quick ignore quick from t')->toString());
    }
}
