<?php

declare(strict_types=1);

namespace Tests\Unit\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Automaton\ParseTableBuilder;
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
final class ParseBranchTest extends TestCase
{
    public function testAdvancePreservesHiddenRulesAndTrailingText(): void
    {
        $builder = new GrammarBuilder();
        $builder->terminal('A');
        $builder->rule('s', ['$@1', 'A']);
        $builder->rule('$@1', [], null, true);
        $table = (new ParseTableBuilder())->build($builder->build())->table;
        $token = new Token($table->symbols->id('A') ?? -1, 'A', 'a', 0);
        $tree = (new \SqlParser\Parser\AlternativeParser($table))->parse([$token, new Token(0, '$end', '', 1, ' trailing')]);
        self::assertNotNull($tree);
        self::assertSame([$token], $tree->children);
        self::assertSame('a trailing', $tree->toString());
    }
}
