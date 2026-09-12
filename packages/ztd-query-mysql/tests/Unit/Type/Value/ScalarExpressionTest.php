<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\Type\Value\ScalarExpression;

#[\PHPUnit\Framework\Attributes\UsesClass(\ZtdQuery\Platform\MySql\Type\Value\StringCoercion::class)]
#[CoversClass(ScalarExpression::class)]
final class ScalarExpressionTest extends TestCase
{
    public function testRenderExpressionPreservesNumbersAndEscapesTextAndBinary(): void
    {
        $renderer = new ScalarExpression();
        $integer = new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, 'INT');
        $text = new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::STRING, 'VARCHAR');
        $binary = new \ZtdQuery\Schema\ColumnType(\ZtdQuery\Schema\ColumnTypeFamily::BINARY, 'BLOB');
        self::assertSame('7', $renderer->renderExpression(7, $integer, false));
        self::assertSame("'7'", $renderer->renderExpression(7, $integer, true));
        self::assertSame("'a''b'", $renderer->renderExpression("a'b", $text, true));
        self::assertSame("CONVERT(X'615c62' USING utf8mb4)", $renderer->renderExpression('a\\b', $text, true));
        self::assertSame("X'0041'", $renderer->renderExpression("\0A", $binary, true));
    }

    public function testInferTypeDistinguishesIntegersFromOtherValues(): void
    {
        $renderer = new ScalarExpression();
        self::assertSame(\ZtdQuery\Schema\ColumnTypeFamily::INTEGER, $renderer->inferType(7)->family);
        self::assertSame(\ZtdQuery\Schema\ColumnTypeFamily::STRING, $renderer->inferType('7')->family);
        self::assertSame(\ZtdQuery\Schema\ColumnTypeFamily::STRING, $renderer->inferType(1.5)->family);
    }

}
