<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Candidate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\FixedLexemeGenerator;
use SqlFaker\Generation\Candidate\VersionCase;
use SqlFaker\Generation\Lexeme\LexemeGenerator;

#[CoversClass(VersionCase::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class VersionCaseTest extends TestCase
{
    public function testSharesOneDefinitionAcrossExplicitReviewedVersions(): void
    {
        $definition = new FixedLexemeGenerator('NOW', 'function', 'lex.h');
        $case = new VersionCase(['mysql-8.4.7', 'mysql-9.1.0'], $definition, 'now');
        self::assertSame($definition, $case->generator);
        self::assertSame(['mysql-8.4.7', 'mysql-9.1.0'], $case->versions);
    }
}
