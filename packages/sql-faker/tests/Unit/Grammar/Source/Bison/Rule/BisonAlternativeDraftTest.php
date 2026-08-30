<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonAlternativeNode;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolForm;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolNode;
use SqlFaker\Grammar\Source\Bison\Rule\BisonAlternativeDraft;

#[CoversClass(BisonAlternativeDraft::class)]
#[UsesClass(BisonAlternativeNode::class)]
#[UsesClass(BisonSymbolNode::class)]
final class BisonAlternativeDraftTest extends TestCase
{
    public function testCompleteYieldsAnEmptyAlternativeWhenNothingWasRead(): void
    {
        $alternative = (new BisonAlternativeDraft())->complete();

        self::assertSame([], $alternative->symbols);
        self::assertNull($alternative->action);
        self::assertNull($alternative->prec);
        self::assertNull($alternative->dprec);
        self::assertNull($alternative->merge);
    }

    public function testAddSymbolKeepsTheOrderTheSymbolsWereRead(): void
    {
        $draft = new BisonAlternativeDraft();
        $first = new BisonSymbolNode(BisonSymbolForm::Identifier, 'expr');
        $second = new BisonSymbolNode(BisonSymbolForm::CharLiteral, '+');

        $draft->addSymbol($first);
        $draft->addSymbol($second);

        self::assertSame([$first, $second], $draft->complete()->symbols);
    }

    public function testSetActionAttachesTheCodeThatRunsOnReduction(): void
    {
        $draft = new BisonAlternativeDraft();

        $draft->setAction('$$ = $1;');

        self::assertSame('$$ = $1;', $draft->complete()->action);
    }

    public function testSetPrecedenceSymbolRecordsTheBorrowedTerminal(): void
    {
        $draft = new BisonAlternativeDraft();

        $draft->setPrecedenceSymbol('UMINUS');

        self::assertSame('UMINUS', $draft->complete()->prec);
    }

    public function testSetDynamicPrecedenceRecordsTheRank(): void
    {
        $draft = new BisonAlternativeDraft();

        $draft->setDynamicPrecedence(2);

        self::assertSame(2, $draft->complete()->dprec);
    }

    public function testSetMergeFunctionRecordsTheFunctionName(): void
    {
        $draft = new BisonAlternativeDraft();

        $draft->setMergeFunction('merge');

        self::assertSame('merge', $draft->complete()->merge);
    }

    public function testCompleteStartsANewAlternativeSoNothingCarriesOver(): void
    {
        $draft = new BisonAlternativeDraft();
        $draft->addSymbol(new BisonSymbolNode(BisonSymbolForm::Identifier, 'expr'));
        $draft->setAction('$$ = $1;');
        $draft->setPrecedenceSymbol('UMINUS');
        $draft->setDynamicPrecedence(2);
        $draft->setMergeFunction('merge');

        $draft->complete();
        $second = $draft->complete();

        self::assertSame([], $second->symbols);
        self::assertNull($second->action);
        self::assertNull($second->prec);
        self::assertNull($second->dprec);
        self::assertNull($second->merge);
    }
}
