<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Construction\Layouts;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(Layouts::class)]
#[Medium]
final class LayoutsTest extends TestCase
{
    public function testOfKeepsEveryTokenAndTheTriviaBetweenThemButNotBeforeTheFirst(): void
    {
        $expression = (new SqliteParser())->parse("SELECT  1 /* one */+\n2")->find('expr')[0];

        $layout = (new Layouts())->of($expression);

        self::assertSame(['1', '+', '2'], array_map(static fn (Spelled $token): string => $token->text, $layout->tokens));
        self::assertSame(['', ' /* one */', "\n"], array_map(static fn (Spelled $token): string => $token->gap, $layout->tokens));
        self::assertSame("1 /* one */+\n2", $layout->text());
    }

    public function testOfRefusesARegionWithoutTokens(): void
    {
        $this->expectExceptionMessage('A spelled region covers at least one token.');

        (new Layouts())->of(new Node('as', 2, []));
    }
}
