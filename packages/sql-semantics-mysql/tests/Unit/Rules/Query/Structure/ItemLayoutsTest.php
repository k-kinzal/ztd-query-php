<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ItemLayouts;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(ItemLayouts::class)]
#[Small]
final class ItemLayoutsTest extends TestCase
{
    public function testPairsFindsNonOverlappingPairsInEachContiguousRun(): void
    {
        $parser = new \SqlParser\MySql\MySqlParser('mysql-8.4.7');

        self::assertSame([0, 4, 6], (new ItemLayouts())->pairs($parser->tokenize('!!1 + !!!!0')));
        self::assertSame([1], (new ItemLayouts())->pairs($parser->tokenize('! !!1')));
        self::assertSame([], (new ItemLayouts())->pairs($parser->tokenize('!/**/!1')));
    }

    public function testCheckRefusesTriviaThatSeparatesANegationPair(): void
    {
        $expression = new Unary(UnaryOperator::PairedNot, new NumberLiteral('1'));
        $layout = new Layout([new Spelled('', '!'), new Spelled(' ', '!'), new Spelled('', '1')]);
        $this->expectExceptionMessage('A select-item layout preserves contiguous negation pairs and their warnings.');

        new SelectExpression($expression, layout: $layout);
    }

    public function testCheckRefusesTriviaThatJoinsIndependentNegations(): void
    {
        $expression = new Unary(UnaryOperator::Not, new Unary(UnaryOperator::Not, new NumberLiteral('1')));
        $layout = new Layout([new Spelled('', '!'), new Spelled('', '!'), new Spelled('', '1')]);
        $this->expectExceptionMessage('A select-item layout preserves contiguous negation pairs and their warnings.');

        new SelectExpression($expression, layout: $layout);
    }

    public function testCheckAcceptsAnAlignedLayout(): void
    {
        $layout = new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '2')]);
        $item = new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: $layout);

        self::assertSame($layout, $item->layout);
    }

    public function testCheckRejectsAnOperandInjectedThroughLegacyLeadingTrivia(): void
    {
        $this->expectExceptionMessage('must preserve the expression');

        new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('e')), layout: new Layout([new Spelled('1 + ', 'e')]), spellingProfile: new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql5651));
    }

    /**
     * @return iterable<string, array{list<Spelled>}>
     */
    public static function providerUnaligned(): iterable
    {
        yield 'shorter' => [[new Spelled('', '1')]];
        yield 'longer' => [[new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '2'), new Spelled('', '3')]];
    }

    /**
     * @param list<Spelled> $tokens
     */
    #[DataProvider('providerUnaligned')]
    public function testCheckRefusesAnUnalignedLayoutAtConstruction(array $tokens): void
    {
        $this->expectExceptionMessage('A select-item layout has one token per expression token.');

        new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: new Layout($tokens));
    }

    /**
     * @return iterable<string, array{list<Spelled>, string}>
     */
    public static function providerInvalidSpellings(): iterable
    {
        yield 'changed operand' => [[new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '3')], 'must preserve the expression'];
        yield 'changed operator' => [[new Spelled('', '1'), new Spelled('', '-'), new Spelled('', '2')], 'must preserve the expression'];
        yield 'syntax in gap' => [[new Spelled('', '1'), new Spelled(' OR 0 ', '+'), new Spelled('', '2')], 'must preserve the expression'];
        yield 'multiple tokens' => [[new Spelled('', '1+0'), new Spelled('', '+'), new Spelled('', '2')], 'exactly one complete token'];
        yield 'leading trivia' => [[new Spelled(' ', '1'), new Spelled('', '+'), new Spelled('', '2')], 'no leading trivia'];
        yield 'redundant layout' => [[new Spelled('', '1'), new Spelled(' ', '+'), new Spelled(' ', '2')], 'other than its canonical rendering'];
        yield 'executable comment' => [[new Spelled('', '1'), new Spelled(' /*!80000 OR 0 */ ', '+'), new Spelled('', '2')], 'must preserve the expression'];
    }

    /**
     * @param list<Spelled> $tokens The attempted spelling of 1 + 2
     * @param string $message The violated constructor invariant
     */
    #[DataProvider('providerInvalidSpellings')]
    public function testInvalidSpellingsAreRejectedBeforeDerivation(array $tokens, string $message): void
    {
        $this->expectExceptionMessage($message);

        new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: new Layout($tokens));
    }

    public function testALayoutCannotBeAttachedToASelfNamedExpression(): void
    {
        $this->expectExceptionMessage('other than its canonical rendering');

        new SelectExpression(new NumberLiteral('1'), layout: new Layout([new Spelled('', '1')]));
    }

    public function testAnItemWithoutALayoutCannotRetainASpellingProfile(): void
    {
        $this->expectExceptionMessage('without a layout keeps no spelling profile');

        new SelectExpression(new NumberLiteral('1'), spellingProfile: new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testALayoutRequiresAMySqlProfile(): void
    {
        $this->expectExceptionMessage('uses a MySQL language profile');

        new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '2')]), spellingProfile: new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::Sqlite3472));
    }

    public function testQuoteInterpretationBelongsToTheSpellingProfile(): void
    {
        $profile = new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847);
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral(['2']));
        $layout = new Layout([new Spelled('', '1'), new Spelled('', '+'), new Spelled('', '"2"')]);
        $item = new SelectExpression($expression, layout: $layout, spellingProfile: $profile);

        self::assertSame($profile, $item->spellingProfile);
        $this->expectExceptionMessage('must preserve the expression');

        new SelectExpression($expression, layout: $layout, spellingProfile: new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847, new \SqlSemantics\Contract\LexicalSettings('ANSI_QUOTES')));
    }
}
