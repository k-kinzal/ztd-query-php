<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Language;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause::class)]
#[Medium]
final class FunctionClauseTest extends TestCase
{
    public function testRenderWritesTheNoForm(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole::Handler))->render($out);
        self::assertSame('NO HANDLER', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheFunction(): void
    {
        self::assertSame('CREATE FOREIGN DATA WRAPPER w VALIDATOR s.v', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE FOREIGN DATA WRAPPER w VALIDATOR s.v')->toString());
    }
}
