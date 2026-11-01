<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertedRow;

#[CoversClass(DeferredInsertedRow::class)]
#[Medium]
final class DeferredInsertedRowTest extends TestCase
{
    public function testRenderRetainsDefaultAndRowOperands(): void
    {
        $row = new DeferredInsertedRow([new \SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest(), new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')])]);
        $semantics = new Semantics(Dialect::MySql);
        $out = new \SqlSemantics\Rendering\Output((new \SqlSemantics\Platform\MySql\Platform())->codec($semantics->profile()));
        $row->render($out);
        self::assertSame('(DEFAULT, (1, 2))', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }
}
