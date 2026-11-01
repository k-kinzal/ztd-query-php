<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Branching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(DeferredCaseBranch::class)]
#[Medium]
final class DeferredCaseBranchTest extends TestCase
{
    public function testRenderKeepsTheWhenAndThenRoles(): void
    {
        $value = new DeferredCaseBranch(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3'));
        $semantics = new Semantics(Dialect::MySql);
        $out = new Output((new \SqlSemantics\Platform\MySql\Platform())->codec($semantics->profile()));
        $value->render($out);
        self::assertSame('WHEN (1, 2) THEN 3', (new Lexical())->join($out->pieces()));
    }
}
