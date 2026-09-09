<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\CommonKeywordDefinitions;
use SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator;
use SqlFaker\Sqlite\Generation\Lexeme\KeywordDefinitions;

#[CoversClass(CommonKeywordDefinitions::class)]
#[UsesClass(KeywordDefinitions::class)]
#[UsesClass(KeywordLexemeGenerator::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator::class)]
final class CommonKeywordDefinitionsTest extends TestCase
{
    public function testCreateUsesOnlyTheExplicitCommonHandlerSet(): void
    {
        $keywords = new KeywordLexemeGenerator(['SELECT_SYM' => ['SELECT'], 'UNREVIEWED' => ['NEWWORD']], []);
        $generator = (new CommonKeywordDefinitions())->create('mysql-8.4.7', $keywords);
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['SELECT_SYM']), 0, new ResolvedOutput(), null));
        self::assertNotNull($result);
        self::assertSame('SELECT', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['UNREVIEWED']), 0, new ResolvedOutput(), null)));
    }
}
