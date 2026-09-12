<?php

declare(strict_types=1);

namespace Tests\Unit\Parsing\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Parsing\Alter\OptionList;

#[CoversClass(OptionList::class)]
final class OptionListTest extends TestCase
{
    public function testHasAnyUsesTheParsersOptionValues(): void
    {
        $options = new \PhpMyAdmin\SqlParser\Components\OptionsArray(['ADD', 'COLUMN']);
        self::assertTrue(OptionList::hasAny($options, ['DROP', 'ADD']));
        self::assertFalse(OptionList::hasAny($options, ['DROP', 'KEY']));
        self::assertFalse(OptionList::hasAny($options, []));
    }

    public function testUnknownKeywordsNormalizesUnparsedTokens(): void
    {
        $operation = new \PhpMyAdmin\SqlParser\Components\AlterOperation();
        $operation->unknown = (new \PhpMyAdmin\SqlParser\Lexer('engine'))->list->tokens;
        self::assertContains('ENGINE', OptionList::unknownKeywords($operation));
        $operation->unknown = '';
        self::assertSame([], OptionList::unknownKeywords($operation));
    }

}
