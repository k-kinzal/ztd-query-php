<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseBranch;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;

#[CoversClass(CaseBranch::class)]
#[Small]
final class CaseBranchTest extends TestCase
{
    public function testRenderWritesWhenAndThen(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new CaseBranch(new BooleanLiteral(true), new NullLiteral()))->render($out);
        self::assertSame('WHEN TRUE THEN NULL', (new Lexical())->join($out->pieces()));
    }
}
