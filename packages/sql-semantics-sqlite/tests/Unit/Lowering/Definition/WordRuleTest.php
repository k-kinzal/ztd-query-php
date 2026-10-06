<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Definition\WordRule;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\Generated;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;

#[CoversClass(WordRule::class)]
#[Medium]
final class WordRuleTest extends TestCase
{
    public function testWordKeepsTheQuotingOfTheToken(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a DEFAULT x, b DEFAULT "x", c DEFAULT [x], d DEFAULT `x`)')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $quotes = array_map(static fn (object $column): ?WordQuote => $column->constraints[0] instanceof DefaultWord ? $column->constraints[0]->word->quote : null, $statement->columns);
        self::assertSame([WordQuote::Bare, WordQuote::Double, WordQuote::Bracket, WordQuote::Backtick], $quotes);
    }

    public function testWordDecodesTheName(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('CREATE TABLE t (a, b AS (a) "my ""word""")')->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        $generated = $statement->columns[1]->constraints[0];
        self::assertInstanceOf(Generated::class, $generated);
        self::assertSame('my "word"', $generated->word?->name->value);
    }

    public function testNameReadsAnIdentifierOrAStringToken(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze("CREATE TABLE t (a) WITHOUT rowid, 'strict'")->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertSame(WordQuote::Bare, $statement->options[0]->word->quote);
        self::assertSame(WordQuote::Single, $statement->options[1]->word->quote);
        self::assertSame('strict', $statement->options[1]->word->name->value);
    }
}
