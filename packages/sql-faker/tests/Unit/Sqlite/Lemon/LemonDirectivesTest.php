<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\Lemon\LemonDirectives;
use SqlFaker\Sqlite\Lemon\LemonSymbols;

#[CoversClass(LemonDirectives::class)]
#[UsesClass(LemonSymbols::class)]
final class LemonDirectivesTest extends TestCase
{
    #[DataProvider('providerDirectiveAndToken')]
    public function testDeclareIntoMakesTheNameATokenEvenWhereARuleIsWrittenForIt(string $input, string $name): void
    {
        $symbols = new LemonSymbols();
        $symbols->declareRule($name);

        (new LemonDirectives())->declareInto($input, $symbols);

        self::assertTrue($symbols->isTerminal($name));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerDirectiveAndToken(): iterable
    {
        yield 'named outright' => ["%token SELECT INSERT.\n", 'INSERT'];
        yield 'given a left precedence' => ["%left OR.\n", 'OR'];
        yield 'given a right precedence' => ["%right NOT.\n", 'NOT'];
        yield 'given no associativity' => ["%nonassoc LT.\n", 'LT'];
        yield 'named as a fallback' => ["%fallback ID ABORT ACTION.\n", 'ACTION'];
        yield 'standing in a class' => ["%token_class anytype INTEGER|TEXT|BLOB.\n", 'BLOB'];
        yield 'named as the wildcard' => ["%wildcard ANY.\n", 'ANY'];
    }

    public function testDeclareIntoReadsAClassNameThatIsNotShapedLikeAToken(): void
    {
        $symbols = new LemonSymbols();
        (new LemonDirectives())->declareInto("%token_class anytype INTEGER|TEXT.\n", $symbols);

        self::assertTrue($symbols->isTerminal('anytype'));
    }

    public function testDeclareIntoLeavesARuleTheDirectivesNeverNameARule(): void
    {
        $symbols = new LemonSymbols();
        $symbols->declareRule('SELECT');

        (new LemonDirectives())->declareInto("%token INSERT.\n", $symbols);

        self::assertFalse($symbols->isTerminal('SELECT'));
    }

    public function testDeclareIntoReadsNothingOutOfAGrammarWithNoDirectives(): void
    {
        $symbols = new LemonSymbols();
        $symbols->declareRule('SELECT');

        (new LemonDirectives())->declareInto("cmd ::= SELECT.\n", $symbols);

        self::assertFalse($symbols->isTerminal('SELECT'));
    }
}
