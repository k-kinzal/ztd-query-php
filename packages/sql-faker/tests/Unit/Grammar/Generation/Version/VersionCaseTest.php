<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Version;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Version\VersionCase;

#[CoversClass(VersionCase::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
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
