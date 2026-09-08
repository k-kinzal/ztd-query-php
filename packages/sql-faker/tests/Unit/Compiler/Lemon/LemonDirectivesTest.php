<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Lemon\LemonDirectives;
use SqlFaker\Compiler\Lemon\LemonSymbols;

#[CoversClass(LemonDirectives::class)]
#[UsesClass(LemonSymbols::class)]
final class LemonDirectivesTest extends TestCase
{
    public function testDeclareIntoReadsTheTokensNamedOutright(): void
    {
        $symbols = new LemonSymbols();
        (new LemonDirectives())->declareInto("%token SELECT INSERT.\n", $symbols);

        self::assertTrue($symbols->isTerminal('INSERT'));
    }

    public function testDeclareIntoReadsTheTokensNamedWhileGivingThemAPrecedence(): void
    {
        $symbols = new LemonSymbols();
        (new LemonDirectives())->declareInto("%left OR.\n%right NOT.\n%nonassoc LT.\n", $symbols);

        self::assertTrue($symbols->isTerminal('OR'));
        self::assertTrue($symbols->isTerminal('NOT'));
        self::assertTrue($symbols->isTerminal('LT'));
    }

    public function testDeclareIntoReadsTheTokensNamedAsAFallback(): void
    {
        $symbols = new LemonSymbols();
        (new LemonDirectives())->declareInto("%fallback ID ABORT ACTION.\n", $symbols);

        self::assertTrue($symbols->isTerminal('ACTION'));
    }

    public function testDeclareIntoReadsAClassAndTheTokensItStandsFor(): void
    {
        $symbols = new LemonSymbols();
        (new LemonDirectives())->declareInto("%token_class anytype INTEGER|TEXT|BLOB.\n", $symbols);

        self::assertFalse($symbols->isTerminal('anytype'));
        self::assertSame(['anytype' => ['INT', 'REAL']], (new LemonDirectives())->tokenClasses('%token_class anytype INT|REAL.'));
        self::assertTrue($symbols->isTerminal('BLOB'));
    }

    public function testDeclareIntoReadsTheWildcardToken(): void
    {
        $symbols = new LemonSymbols();
        (new LemonDirectives())->declareInto("%wildcard ANY.\n", $symbols);

        self::assertTrue($symbols->isTerminal('ANY'));
    }

    public function testTokenClassesRetainsEveryAlternativeAcrossLines(): void
    {
        self::assertSame(['id' => ['ID', 'INDEXED'], 'number' => ['INTEGER', 'FLOAT']], (new LemonDirectives())->tokenClasses("%token_class id ID|INDEXED.\n%token_class number INTEGER\n FLOAT."));
    }

}
