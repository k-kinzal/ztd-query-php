<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Token;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\TerminalOccurrence;

#[CoversClass(TerminalOccurrence::class)]

final class TerminalOccurrenceTest extends TestCase
{
    public function testWithinRecognizesOnlyDeclaredAncestors(): void
    {
        $terminal = new TerminalOccurrence('IDENT', 3, [0, 2], ['stmt', 'name']);
        self::assertTrue($terminal->within('stmt'));
        self::assertFalse($terminal->within('IDENT'));
    }

    public function testAncestorSelectsTheNearestRecursiveOccurrence(): void
    {
        $terminal = new TerminalOccurrence('IDENT', 5, [0, 1, 2], ['expr', 'name', 'expr']);
        self::assertSame(2, $terminal->ancestor('expr'));
        self::assertSame(1, $terminal->ancestor('name'));
        self::assertNull($terminal->ancestor('stmt'));
    }

    public function testReplacedPreservesIdentityAndScopeWithoutMutatingItsSource(): void
    {
        $terminal = new TerminalOccurrence('IDENT', 3, [0, 2], ['stmt', 'name']);
        $result = $terminal->replaced('MODE', 'source:mode');
        self::assertSame('IDENT', $terminal->name);
        self::assertSame('MODE', $result->name);
        self::assertSame($terminal->id, $result->id);
        self::assertSame($terminal->ancestors, $result->ancestors);
        self::assertSame($terminal->rules, $result->rules);
        self::assertSame('source:mode', $result->rewrite);
    }
}
