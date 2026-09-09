<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Version;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;

#[CoversClass(VersionedLexemeGenerator::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class VersionedLexemeGeneratorTest extends TestCase
{
    public function testGenerateFromAnUnselectedCaseNeverGeneratesAndSharedDefinitionsRemainReusable(): void
    {
        $unused = $this->createMock(LexemeGenerator::class);
        $unused->expects(self::never())->method('generate');
        $shared = new FixedLexemeGenerator('NOW', 'function', 'shared');
        $old = new VersionCase(['demo-1', 'demo-2'], $shared, 'shared-case');
        $new = new VersionCase(['demo-3'], $unused, 'new-case');
        $input = new LexemeInput(TerminalSequence::fromNames(['NOW_SYM']), 0, new ResolvedOutput());
        $first = new VersionedLexemeGenerator('demo-1', $old, $new);
        $second = new VersionedLexemeGenerator('demo-2', $old, $new);
        self::assertSame($old, $first->selected);
        self::assertSame($old, $second->selected);
        self::assertSame($old, (new VersionedLexemeGenerator('demo-1', $new, $old))->selected);
        $firstResult = $first->generate($input);
        $secondResult = $second->generate($input);
        self::assertNotNull($firstResult);
        self::assertNotNull($secondResult);
        self::assertSame('NOW', [...$firstResult->sequences()][0]->lexemes[0]->text);
        self::assertSame(['shared:NOW', 'version-case:shared-case'], [...[...$firstResult->sequences()][0]->sources()]);
        self::assertSame('NOW', [...$secondResult->sequences()][0]->lexemes[0]->text);
        self::assertNull((new VersionedLexemeGenerator('unknown', $old, $new))->generate($input));
    }

    public function testOverlappingCasesAreAnErrorEvenWhenTheyShareAnImplementation(): void
    {
        $fixed = new FixedLexemeGenerator('NOW', 'function', 'shared');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Overlapping version cases for demo: first, second');
        new VersionedLexemeGenerator('demo', new VersionCase(['demo'], $fixed, 'first'), new VersionCase(['demo'], $fixed, 'second'));
    }
}
