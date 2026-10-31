<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\MySql\MySqlParser;
use SqlParser\Parser\Node;
use SqlParser\Parser\SyntaxException;

#[CoversNothing]
final class ReductionParserTest extends TestCase
{
    public function testParseObservesCompletedProductionsWithoutChangingTheTree(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');
        $reductions = [];
        $sql = ' SELECT BINARY 1 /* trailing */ ';
        $tree = $parser->parse($sql, static function (Node $node, Token $lookahead) use (&$reductions): void {
            $reductions[] = [$node->name, $lookahead->offset];
        });

        self::assertSame($sql, $tree->toString());
        self::assertSame($parser->parse($sql)->toString(), $tree->toString());
        self::assertContains('simple_expr', array_column($reductions, 0));
    }

    public function testParseReportsOnlyReductionsCompletedBeforeTheSyntaxError(): void
    {
        $parser = new MySqlParser('mysql-8.4.7');
        $binaries = [];
        try {
            $parser->parse('SELECT BINARY 1, ) BINARY 2', static function (Node $node, Token $lookahead) use (&$binaries): void {
                $first = $node->children[0] ?? null;
                if ($node->name === 'simple_expr' && $first instanceof Token && $first->name === 'BINARY_SYM') {
                    $binaries[] = [$node->toString(), $lookahead->text];
                }
            });
            self::fail('The original syntax error must still be raised.');
        } catch (SyntaxException $error) {
            self::assertSame(')', $error->token->text);
            self::assertSame([[' BINARY 1', ',']], $binaries);
        }
    }
}
