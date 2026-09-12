<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\LexicalDelimiters;
use ZtdQuery\Sql\LexicalPattern;

#[CoversClass(LexicalDelimiters::class)]
#[UsesClass(LexicalPattern::class)]
#[UsesClass(InvalidDefinitionException::class)]
final class LexicalDelimitersTest extends TestCase
{
    public function testNonEmptyAnswersTheDelimitersItWasGiven(): void
    {
        self::assertSame(['--', '#'], (new LexicalDelimiters())->nonEmpty(['--', '#']));
    }

    public function testPairsAnswersThePairsItWasGiven(): void
    {
        self::assertSame(['/*' => '*/'], (new LexicalDelimiters())->pairs(['/*' => '*/'], 'Block comment'));
    }

    public function testPairsNamesWhatItWasRefusingInTheRefusal(): void
    {
        $this->expectExceptionMessage('String quote delimiters must not be empty.');

        (new LexicalDelimiters())->pairs(['' => "'"], 'String quote');
    }

    public function testPerPrefixListsAnswersTheListsItWasGiven(): void
    {
        self::assertSame([':' => ['.']], (new LexicalDelimiters())->perPrefixLists([':' => ['.']]));
    }

    public function testPerPrefixPatternsAnswersThePatternsItWasGiven(): void
    {
        self::assertSame(
            [':' => '/^\d+/'],
            (new LexicalDelimiters())->perPrefixPatterns([':' => '/^\d+/']),
        );
    }

    public function testValidPatternsAnswersThePatternsItWasGiven(): void
    {
        self::assertSame(['/^\d+/'], (new LexicalDelimiters())->validPatterns(['/^\d+/']));
    }

}
