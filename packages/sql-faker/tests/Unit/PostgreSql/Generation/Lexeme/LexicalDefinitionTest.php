<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\FixedLexemeGenerator;
use SqlFaker\Generation\Output\CombinedSpacingRule;
use SqlFaker\Generation\Output\SqlSerializer;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Lexeme\LexicalDefinition;

#[CoversClass(LexicalDefinition::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\OutputPart::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
final class LexicalDefinitionTest extends TestCase
{
    public function testCompositionProducesOutputAndKeepsItsDiagnosticMetadata(): void
    {
        $lexemes = new FixedLexemeGenerator('SELECT', 'keyword', 'scanner:select');
        $definition = new LexicalDefinition(
            'demo-1',
            'Demo',
            $lexemes,
            new CombinedSpacingRule(),
            ['SELECT' => ['SELECT']],
            ['EOF'],
        );
        $output = $definition->pipeline->generate(TerminalSequence::fromNames(['SELECT']), null, static fn (int $count): int => 0);

        self::assertSame('SELECT', (new SqlSerializer())->serialize($output->pieces()));
        self::assertSame($lexemes, $definition->lexemes);
        self::assertSame(['SELECT' => ['SELECT']], $definition->keywords);
        self::assertSame(['EOF'], $definition->nonOutput);
    }
}
