<?php

declare(strict_types=1);

namespace Tests\Unit\Shadow\Mutation\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Shadow\Mutation\Table\Alter\UnsupportedKeyword;

#[CoversClass(UnsupportedKeyword::class)]
final class UnsupportedKeywordTest extends TestCase
{
    public function testHasUnsupportedKeywordInUnknown(): void
    {
        $op = new \PhpMyAdmin\SqlParser\Components\AlterOperation();
        $op->unknown = (new \PhpMyAdmin\SqlParser\Lexer('PARTITION p0'))->list->tokens;
        self::assertTrue((new UnsupportedKeyword())->hasUnsupportedKeywordInUnknown($op));
        $op->unknown = (new \PhpMyAdmin\SqlParser\Lexer('name TEXT'))->list->tokens;
        self::assertFalse((new UnsupportedKeyword())->hasUnsupportedKeywordInUnknown($op));
        $op->unknown = '';
        self::assertFalse((new UnsupportedKeyword())->hasUnsupportedKeywordInUnknown($op));
    }

}
