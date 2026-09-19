<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\GrammarException;
use SqlParser\Grammar\SymbolTable;

#[CoversClass(SymbolTable::class)]
#[UsesClass(GrammarException::class)]
#[Small]
final class SymbolTableTest extends TestCase
{
    public function testId(): void
    {
        $table = new SymbolTable(['$end', 'NUM', '+'], ['$accept', 'expr']);

        self::assertSame(0, $table->id('$end'));
        self::assertSame(2, $table->id('+'));
        self::assertSame(4, $table->id('expr'));
        self::assertNull($table->id('missing'));
    }

    public function testName(): void
    {
        $table = new SymbolTable(['$end', 'NUM'], ['$accept', 'expr']);

        self::assertSame('NUM', $table->name(1));
        self::assertSame('expr', $table->name(3));
    }

    public function testNameRejectsAnUnknownNumber(): void
    {
        $this->expectException(GrammarException::class);

        (new SymbolTable(['$end'], ['$accept']))->name(2);
    }

    public function testNameRejectsANegativeNumber(): void
    {
        $this->expectException(GrammarException::class);

        (new SymbolTable(['$end'], ['$accept']))->name(-1);
    }

    public function testIsTerminal(): void
    {
        $table = new SymbolTable(['$end', 'NUM'], ['$accept', 'expr']);

        self::assertTrue($table->isTerminal(1));
        self::assertFalse($table->isTerminal(2));
    }

    public function testTerminalCount(): void
    {
        self::assertSame(2, (new SymbolTable(['$end', 'NUM'], ['$accept']))->terminalCount());
    }

    public function testCount(): void
    {
        self::assertSame(4, (new SymbolTable(['$end', 'NUM'], ['$accept', 'expr']))->count());
    }

    public function testTerminals(): void
    {
        self::assertSame(['$end', 'NUM'], (new SymbolTable(['$end', 'NUM'], ['$accept']))->terminals());
    }

    public function testNonterminals(): void
    {
        self::assertSame(['$accept', 'expr'], (new SymbolTable(['$end'], ['$accept', 'expr']))->nonterminals());
    }

    public function testTerminalsMustStartWithTheEndMarker(): void
    {
        $this->expectException(GrammarException::class);

        new SymbolTable(['NUM'], ['$accept']);
    }

    public function testNamesMustBeUnique(): void
    {
        $this->expectException(GrammarException::class);

        new SymbolTable(['$end', 'x'], ['x']);
    }
}
