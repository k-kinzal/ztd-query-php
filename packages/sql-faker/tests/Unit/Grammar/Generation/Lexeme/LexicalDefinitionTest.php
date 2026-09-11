<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexicalDefinition;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(LexicalDefinition::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
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
            ['NOW' => ['NOW']],
            ['EOF'],
            ['demo-1'],
        );
        $output = $definition->pipeline->generate(TerminalSequence::fromNames(['SELECT']), null, static fn (int $count): int => 0);

        self::assertSame('SELECT', (new SqlSerializer())->serialize($output->pieces()));
        self::assertSame($lexemes, $definition->lexemes);
        self::assertSame(['SELECT' => ['SELECT']], $definition->keywords);
        self::assertSame(['NOW' => ['NOW']], $definition->functions);
        self::assertSame(['EOF'], $definition->nonOutput);
        self::assertSame(['demo-1'], $definition->dollarVersions);
    }
}
