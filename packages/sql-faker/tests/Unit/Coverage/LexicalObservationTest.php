<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Coverage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Coverage\LexicalObservation;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;

#[CoversClass(LexicalObservation::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class LexicalObservationTest extends TestCase
{
    public function testFeaturesKeepsValueTextOutOfFiniteDefinitionSets(): void
    {
        $origin = new TerminalOccurrence('COMPOUND', 0);
        $first = new Lexeme('random-123', 'identifier', $origin, 'identifier-domain');
        $second = new Lexeme('random-456', 'identifier', $origin, 'identifier-domain');
        $candidate = new LexemeSequence([$first, $second], 'random-id', provenance: static fn (): array => ['source-a', 'version-case:release-17']);
        $output = new ResolvedOutput([new OutputPart($first, ' ', 'random-id', ['phrase']), new OutputPart($second, '', 'random-id')], candidates: [$candidate]);
        self::assertSame(['lexeme' => ['identifier-domain'], 'compound' => ['identifier-domain + identifier-domain'],
            'version-case' => ['release-17'], 'spacing' => ['phrase:space', 'default:join']], (new LexicalObservation())->features($output));
        self::assertSame(['lexeme' => [], 'compound' => [], 'version-case' => [], 'spacing' => []], (new LexicalObservation())->features(new ResolvedOutput()));
    }

    public function testSourcesRetainsAllCandidateProvenanceInOccurrenceOrder(): void
    {
        $output = new ResolvedOutput(candidates: [new LexemeSequence([], 'empty', provenance: static fn (): array => ['first', 'second', 'version-case:chosen'])]);
        self::assertSame([['first', 'second', 'version-case:chosen']], (new LexicalObservation())->sources($output));
    }

    public function testConditionsRetainsTheInputMasksAndAllContributingRules(): void
    {
        $output = new ResolvedOutput(candidates: [new LexemeSequence([], 'empty', new SpacingConstraint(1, ['left']), [0 => new SpacingConstraint(2, ['internal'])]), new LexemeSequence([], 'unconstrained')]);
        self::assertSame([['left' => ['allowed' => 1, 'rules' => ['left']], 'boundaries' => [['allowed' => 2, 'rules' => ['internal']]]], ['left' => null, 'boundaries' => []]], (new LexicalObservation())->conditions($output));
    }

    public function testRewritesRetainsEachIntermediateReplacementAndOriginalIdentity(): void
    {
        $original = new TerminalOccurrence('A', 7, [2], ['root']);
        $sequence = new TerminalSequence([$original], [$original]);
        $sequence = $sequence->replace(0, 1, [$original->replaced('B', 'first')], 'first');
        $sequence = $sequence->replace(0, 1, [$sequence->terminals[0]->replaced('C', 'second')], 'second');
        $operations = (new LexicalObservation())->rewrites($sequence);
        self::assertSame('A', $operations[0]['removed'][0]['name']);
        self::assertSame('B', $operations[0]['inserted'][0]['name']);
        self::assertSame($operations[0]['inserted'], $operations[1]['removed']);
        self::assertSame('C', $operations[1]['inserted'][0]['name']);
        self::assertSame(['id' => 7, 'name' => 'C', 'ancestors' => [2], 'rules' => ['root'], 'rewrite' => 'second'], $operations[1]['inserted'][0]);
    }
}
