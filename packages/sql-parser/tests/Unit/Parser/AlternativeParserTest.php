<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\ParseTableBuilder;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\GrammarBuilder;
use SqlParser\Lexer\Token;
use SqlParser\Parser\LrParser;
use SqlParser\Parser\Node;
use SqlParser\Parser\SyntaxException;

#[CoversClass(LrParser::class)]
#[CoversClass(Node::class)]
#[CoversClass(SyntaxException::class)]
#[CoversClass(Token::class)]
#[CoversClass(GrammarBuilder::class)]
#[CoversClass(ParseTableBuilder::class)]
#[CoversClass(\SqlParser\Automaton\Bitset::class)]
#[CoversClass(\SqlParser\Automaton\BuildResult::class)]
#[CoversClass(\SqlParser\Automaton\ClosureIndex::class)]
#[CoversClass(\SqlParser\Automaton\ConflictResolver::class)]
#[CoversClass(\SqlParser\Automaton\ConflictSummary::class)]
#[CoversClass(\SqlParser\Automaton\Digraph::class)]
#[CoversClass(\SqlParser\Automaton\LookaheadSets::class)]
#[CoversClass(\SqlParser\Automaton\Lr0Automaton::class)]
#[CoversClass(\SqlParser\Automaton\Lr0Builder::class)]
#[CoversClass(\SqlParser\Automaton\NullableSet::class)]
#[CoversClass(\SqlParser\Automaton\ResolvedState::class)]
#[CoversClass(\SqlParser\Grammar\Grammar::class)]
#[CoversClass(\SqlParser\Grammar\Precedence::class)]
#[CoversClass(\SqlParser\Grammar\Rule::class)]
#[CoversClass(\SqlParser\Grammar\SymbolTable::class)]
#[CoversClass(\SqlParser\Lexer\SourcePosition::class)]
#[CoversClass(\SqlParser\Table\ActionCode::class)]
#[CoversClass(\SqlParser\Table\ArrayRows::class)]
#[CoversClass(\SqlParser\Table\ParseTable::class)]
#[CoversClass(\SqlParser\Table\TableRule::class)]
#[CoversClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
#[CoversClass(\SqlParser\Parser\AlternativeParser::class)]
#[CoversClass(\SqlParser\Parser\ParseBranch::class)]
#[CoversClass(\SqlParser\Table\AlternativeCodec::class)]
final class AlternativeParserTest extends TestCase
{
    public function testParseExploresAnUnrankedShiftReduceAlternative(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->terminal('B');
        $builder->rule('s', ['A', 'A']);
        $builder->rule('s', ['empty', 'A', 'B']);
        $builder->rule('empty', []);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $tokens = [new Token($table->symbols->id('A') ?? -1, 'A', 'a', 0), new Token($table->symbols->id('B') ?? -1, 'B', 'b', 2, ' '), new Token(0, '$end', '', 3, '  ')];
        $tree = (new LrParser($table))->parse($tokens, 'a b  ');
        self::assertSame('s', $tree->name);
        self::assertSame(1, $tree->ordinal);
        self::assertCount(1, $tree->find('empty'));
        self::assertSame('a b  ', $tree->toString());
        self::assertSame([0 => [$table->symbols->id('A') => [-4]]], $table->alternatives);
    }

    public function testParseReturnsNullWhenNoDerivationExists(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->terminal('B');
        $builder->rule('s', ['A', 'A']);
        $builder->rule('s', ['empty', 'A', 'B']);
        $builder->rule('empty', []);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        self::assertNull((new \SqlParser\Parser\AlternativeParser($table))->parse([new Token($table->symbols->id('B') ?? -1, 'B', 'b', 0)]));
        $this->expectException(SyntaxException::class);
        (new LrParser($table))->parse([new Token($table->symbols->id('A') ?? -1, 'A', 'a', 0)], 'a');
    }

    public function testParseRetainsExplicitNonassociativeErrors(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->precedence(['='], Associativity::NonAssoc);
        $builder->rule('s', ['s', '=', 's']);
        $builder->rule('s', ['A']);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $a = new Token($table->symbols->id('A') ?? -1, 'A', 'a', 0);
        $eq = new Token($table->symbols->id('=') ?? -1, '=', '=', 1);
        self::assertSame([], $table->alternatives);
        self::assertNull((new \SqlParser\Parser\AlternativeParser($table))->parse([$a, $eq, $a, $eq, $a]));
    }
}
