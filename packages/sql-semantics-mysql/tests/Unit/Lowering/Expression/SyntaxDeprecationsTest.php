<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Expression\SyntaxDeprecations;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(SyntaxDeprecations::class)]
#[Small]
final class SyntaxDeprecationsTest extends TestCase
{
    public function testJsonTablePreservesResponseOrderBeforeTargetRefusal(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
        $tree = $parser->parse("DELETE missing FROM JSON_TABLE('[]', '$[*]' COLUMNS(a INT PATH '$' ERROR ON ERROR ERROR ON EMPTY)) AS j");
        $node = $tree->find('opt_on_empty_or_error_json_table')[0];
        self::assertSame(Deprecated::JsonTableResponseOrder, (new SyntaxDeprecations())->completed($node, GrammarRelease::MySql847));
        $normal = $parser->parse("SELECT * FROM JSON_TABLE('[]', '$[*]' COLUMNS(a INT PATH '$' ERROR ON EMPTY ERROR ON ERROR)) AS j");
        self::assertNull((new SyntaxDeprecations())->completed($normal->find('opt_on_empty_or_error_json_table')[0], GrammarRelease::MySql847));
    }

    public function testStopsBinaryFollowsOnlyTheDirectCastOperand(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');
        $nodes = array_values(array_filter($parser->parse('SELECT BINARY ((CAST(1 AT LOCAL AS SIGNED)))')->find('simple_expr'), static fn (Node $node): bool => ($node->tokens()[0]->text ?? '') === 'BINARY'));
        self::assertNotEmpty($nodes);
        $rules = new SyntaxDeprecations();

        self::assertTrue($rules->stopsBinary($nodes[0]));
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(Deprecated::BinaryOperator, aborts: true), $rules->completed($nodes[0], GrammarRelease::MySql847));
        self::assertFalse($rules->stopsBinary(new Node('simple_expr', 0, [])));
    }

    public function testCompletedKeepsTheReleaseAndOriginalTextLiteralReduction(): void
    {
        $rules = new SyntaxDeprecations();
        $first = new Node('text_literal', 0, [new Token(0, 'NCHAR_STRING', "N'a'", 0)]);
        $continuation = new Node('text_literal', 0, [$first, new Token(0, 'TEXT_STRING', "'b'", 5)]);

        self::assertSame(Deprecated::National, $rules->completed($first, GrammarRelease::MySql847));
        self::assertNull($rules->completed($first, GrammarRelease::MySql5744));
        self::assertNull($rules->completed($continuation, GrammarRelease::MySql847));
    }
}
