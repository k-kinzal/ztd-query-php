<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\DefaultWord;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(DefaultWord::class)]
#[Medium]
final class DefaultWordTest extends TestCase
{
    public function testTruthReadsAnUnquotedTrueOrFalse(): void
    {
        self::assertTrue((new DefaultWord(new Word(new Name('TRUE'))))->truth());
        self::assertFalse((new DefaultWord(new Word(new Name('false'))))->truth());
    }

    public function testTruthIsNullForATextDefault(): void
    {
        self::assertNull((new DefaultWord(new Word(new Name('unknown'))))->truth());
        self::assertNull((new DefaultWord(new Word(new Name('true'), WordQuote::Double)))->truth());
    }

    public function testDeriveConstraintNeverReadsTheWordAsAColumn(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE t (a, b DEFAULT a)', []);

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderKeepsTheQuotingThatDecidesTheMeaning(): void
    {
        $semantics = new Inspection();
        $truth = $semantics->inspect('create table t (a default true)');
        $text = $semantics->inspect('create table t (a default "true")');
        $statement = $truth->statement;
        $other = $text->statement;

        self::assertInstanceOf(CreateTable::class, $statement);
        self::assertInstanceOf(CreateTable::class, $other);
        self::assertInstanceOf(DefaultWord::class, $statement->columns[0]->constraints[0]);
        self::assertInstanceOf(DefaultWord::class, $other->columns[0]->constraints[0]);
        self::assertTrue($statement->columns[0]->constraints[0]->truth());
        self::assertNull($other->columns[0]->constraints[0]->truth());
        self::assertSame('CREATE TABLE t (a DEFAULT true)', $truth->toString());
        self::assertSame('CREATE TABLE t (a DEFAULT "true")', $text->toString());
    }
}
