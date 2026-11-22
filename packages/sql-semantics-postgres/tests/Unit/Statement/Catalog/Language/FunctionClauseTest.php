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
        $out = new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionRole::Handler))->render($out);
        self::assertSame('NO HANDLER', (new \SqlSemantics\Platform\PostgreSql\Model\Rendering\Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheFunction(): void
    {
        self::assertSame('CREATE FOREIGN DATA WRAPPER w VALIDATOR s.v', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('CREATE FOREIGN DATA WRAPPER w VALIDATOR s.v')->toString());
    }
}
