<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowKeys;

#[CoversClass(ShowKeys::class)]
#[Medium]
final class ShowKeysTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableInTheWrittenDatabase(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW EXTENDED INDEX FROM t FROM db');
        self::assertInstanceOf(ShowKeys::class, $show);
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE db.t (a INT)');
        $keys = $semantics->analyze('SHOW INDEX FROM other.t IN db', [$table]);
        self::assertInstanceOf(ShowKeys::class, $keys);
        self::assertSame([], $keys->diagnostics());
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW EXTENDED INDEX FROM t FROM db');
        self::assertInstanceOf(ShowKeys::class, $show);
        self::assertSame('Visible', $show->relationInformation()->shape->slots[13]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW EXTENDED INDEXES FROM t FROM db', (new Semantics(Dialect::MySql))->analyze('SHOW EXTENDED INDEX FROM t FROM db')->toString());
    }
}
