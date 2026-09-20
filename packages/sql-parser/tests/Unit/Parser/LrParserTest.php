<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\ParseTableBuilder;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Lexer\Token;
use SqlParser\Parser\LrParser;
use SqlParser\Parser\Node;
use SqlParser\Parser\SyntaxException;

#[CoversClass(LrParser::class)]
#[UsesClass(Node::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(GrammarBuilder::class)]
#[UsesClass(ParseTableBuilder::class)]
#[UsesClass(\SqlParser\Automaton\Bitset::class)]
#[UsesClass(\SqlParser\Automaton\BuildResult::class)]
#[UsesClass(\SqlParser\Automaton\ClosureIndex::class)]
#[UsesClass(\SqlParser\Automaton\ConflictResolver::class)]
#[UsesClass(\SqlParser\Automaton\ConflictSummary::class)]
#[UsesClass(\SqlParser\Automaton\Digraph::class)]
#[UsesClass(\SqlParser\Automaton\LookaheadSets::class)]
#[UsesClass(\SqlParser\Automaton\Lr0Automaton::class)]
#[UsesClass(\SqlParser\Automaton\Lr0Builder::class)]
#[UsesClass(\SqlParser\Automaton\NullableSet::class)]
#[UsesClass(\SqlParser\Automaton\ResolvedState::class)]
#[UsesClass(\SqlParser\Grammar\Grammar::class)]
#[UsesClass(\SqlParser\Grammar\Precedence::class)]
#[UsesClass(\SqlParser\Grammar\Rule::class)]
#[UsesClass(\SqlParser\Grammar\SymbolTable::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Table\ActionCode::class)]
#[UsesClass(\SqlParser\Table\ArrayRows::class)]
#[UsesClass(\SqlParser\Table\ParseTable::class)]
#[UsesClass(\SqlParser\Table\TableRule::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
final class LrParserTest extends TestCase
{
    public function testParseBuildsATreeNamedAfterTheRules(): void
    {
        $builder = new GrammarBuilder();
        $builder->precedence(['+'], Associativity::Left);
        $builder->precedence(['*'], Associativity::Left);
        $builder->terminal('NUM');
        $builder->rule('expr', ['expr', '+', 'expr']);
        $builder->rule('expr', ['expr', '*', 'expr']);
        $builder->rule('expr', ['NUM']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $symbols = $table->symbols;
        $num = $symbols->id('NUM') ?? -1;
        $sql = '1+2*3';
        $tokens = [new Token($num, 'NUM', '1', 0), new Token($symbols->id('+') ?? -1, '+', '+', 1), new Token($num, 'NUM', '2', 2), new Token($symbols->id('*') ?? -1, '*', '*', 3), new Token($num, 'NUM', '3', 4), new Token(0, '$end', '', 5)];
        $tree = (new LrParser($table))->parse($tokens, $sql);

        self::assertSame('expr', $tree->name);
        self::assertSame(0, $tree->ordinal);
        self::assertSame('1', $tree->find('expr')[1]->text($sql));
        self::assertSame('2*3', $tree->find('expr')[2]->text($sql));
        self::assertSame($sql, $tree->text($sql));
    }

    public function testParseLeavesMidRuleActionsOutOfTheTree(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('$@1', [], null, true);
        $builder->rule('s', ['A', '$@1', 'A']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $a = $table->symbols->id('A') ?? -1;
        $tree = (new LrParser($table))->parse([new Token($a, 'A', 'a', 0), new Token($a, 'A', 'a', 1), new Token(0, '$end', '', 2)], 'aa');

        self::assertCount(2, $tree->children);
        self::assertSame('aa', $tree->text('aa'));
    }

    public function testParseGivesTheTreeTheTextThatFollowsTheLastToken(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('NUM');
        $builder->rule('expr', ['NUM']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $sql = " 1  -- done\n";
        $tokens = [new Token($table->symbols->id('NUM') ?? -1, 'NUM', '1', 1, ' '), new Token(0, '$end', '', 12, "  -- done\n")];
        $tree = (new LrParser($table))->parse($tokens, $sql);

        self::assertSame("  -- done\n", $tree->trailing);
        self::assertSame($sql, $tree->toString());
    }

    public function testParseRejectsAnUnexpectedToken(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->terminal('B');
        $builder->rule('s', ['A', 'B']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $a = $table->symbols->id('A') ?? -1;

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Unexpected 'a' at line 1, column 2, expected B");

        (new LrParser($table))->parse([new Token($a, 'A', 'a', 0), new Token($a, 'A', 'a', 1), new Token(0, '$end', '', 2)], 'aa');
    }

    public function testParseSuppliesTheEndMarkerWhenTheStreamLacksIt(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('s', ['A']);
        $builder->rule('s', []);
        $table = (new ParseTableBuilder())->build($builder->build())->table;

        self::assertTrue((new LrParser($table))->parse([], '')->isEmpty());
        self::assertSame(1, (new LrParser($table))->parse([new Token($table->symbols->id('A') ?? -1, 'A', 'a', 0)], 'a')->children === [] ? 0 : 1);
    }

    public function testExpected(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->terminal('B');
        $builder->rule('s', ['A']);
        $builder->rule('s', ['B']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;

        self::assertSame(['A', 'B'], (new LrParser($table))->expected(0));
    }
}
