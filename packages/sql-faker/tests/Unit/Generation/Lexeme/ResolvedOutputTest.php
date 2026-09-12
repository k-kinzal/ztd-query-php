<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Token\TerminalOccurrence;

#[CoversClass(ResolvedOutput::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
final class ResolvedOutputTest extends TestCase
{
    public function testPiecesPreservesChosenJoinsAndSpacesWithoutFormatting(): void
    {
        $origin = new TerminalOccurrence('IDENT', 0);
        $output = new ResolvedOutput([
            new OutputPart(new Lexeme('a', 'identifier', $origin, 'source'), '', 'a'),
            new OutputPart(new Lexeme('.', 'symbol', $origin, 'source'), ' ', 'dot'),
            new OutputPart(new Lexeme('b', 'identifier', $origin, 'source'), '', 'b'),
        ]);
        self::assertSame(['a', '', '.', ' ', 'b', ''], $output->pieces());
        self::assertSame([], (new ResolvedOutput())->pieces());
    }
}
