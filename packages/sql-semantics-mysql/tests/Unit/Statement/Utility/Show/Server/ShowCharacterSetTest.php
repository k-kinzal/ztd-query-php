<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCharacterSet;

#[CoversClass(ShowCharacterSet::class)]
#[Medium]
final class ShowCharacterSetTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW CHARSET');
        self::assertInstanceOf(ShowCharacterSet::class, $show->statement);
        self::assertSame('Maxlen', $show->field(3)->name?->value);
    }

    public function testDeriveRelationShapesTheResultColumns(): void
    {
        $show = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW CHARSET');
        self::assertInstanceOf(ShowCharacterSet::class, $show->statement);
        self::assertSame('Charset', $show->facts->relation($show->statement)->shape->slots[0]->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CHARSET', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SHOW CHARSET')->toString());
    }
}
