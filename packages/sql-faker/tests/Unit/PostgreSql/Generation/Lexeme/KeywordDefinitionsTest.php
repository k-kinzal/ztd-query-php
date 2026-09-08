<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Lexeme\KeywordDefinitions;

#[CoversClass(KeywordDefinitions::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
final class KeywordDefinitionsTest extends TestCase
{
    public function testCreateDeclaresHandlersIndependentlyOfNewRegistrationNames(): void
    {
        $generator = (new KeywordDefinitions())->create('pg-17.2', ['SELECT' => ['SELECT'], 'UNREVIEWED' => ['NEWWORD']]);
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['SELECT']), 0, new ResolvedOutput(), null));
        self::assertNotNull($result);
        self::assertSame('SELECT', [...$result->sequences()][0]->lexemes[0]->text);
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['UNREVIEWED']), 0, new ResolvedOutput(), null)));
        self::assertNull((new KeywordDefinitions())->create('future-version', ['SELECT' => ['SELECT']])->generate(new LexemeInput(TerminalSequence::fromNames(['SELECT']), 0, new ResolvedOutput(), null)));
    }
}
