<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchClause::class)]
#[Small]
final class SearchClauseTest extends TestCase
{
    public function testRenderWritesTheClause(): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\PostgreSql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::PostgreSql172));
        (new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchClause(\SqlSemantics\Platform\PostgreSql\Statement\Query\With\SearchOrder::Breadth, [new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\Name('b')], new \SqlSemantics\Statement\Identifier\Name('s')))->render($out);
        self::assertSame('SEARCH BREADTH FIRST BY a, b SET s', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }
}
