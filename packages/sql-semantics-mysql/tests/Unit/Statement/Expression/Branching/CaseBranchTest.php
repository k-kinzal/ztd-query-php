<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Branching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(CaseBranch::class)]
#[Medium]
final class CaseBranchTest extends TestCase
{
    public function testRenderWritesWhenAndThen(): void
    {
        $platform = new Platform();
        $out = new Output($platform->codec($platform->profile('mysql-8.4.7', null, ParameterStyle::Native)));
        (new CaseBranch(new NumberLiteral('1'), new NumberLiteral('2')))->render($out);

        self::assertSame('WHEN 1 THEN 2', (new Lexical())->join($out->pieces()));
    }
}
