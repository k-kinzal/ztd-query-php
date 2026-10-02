<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Construction\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Literal\UnsignedInteger;

#[CoversClass(C\Rendering\ExpressionSql::class)]
#[Small]
final class ExpressionSqlTest extends TestCase
{
    public function testWritePreservesSimpleCaseEvaluationStructure(): void
    {
        $input = new C\Conditional\SimpleCaseInput(new E\SqliteInteger(new UnsignedInteger('2')), new C\Conditional\CaseBranchesInput(new E\NullConstant(), new C\Conditional\CaseArmInput(new E\SqliteInteger(new UnsignedInteger('2')), new E\SqliteInteger(new UnsignedInteger('7')))));
        self::assertSame('CASE 2 WHEN 2 THEN 7 ELSE NULL END', (new C\Rendering\ExpressionSql())->write($input));
    }

    public function testBranchesRetainsMissingElse(): void
    {
        $input = new C\Conditional\CaseBranchesInput(null, new C\Conditional\CaseArmInput(new E\NullConstant(), new E\SqliteInteger(new UnsignedInteger('7'))));
        self::assertSame('WHEN NULL THEN 7', (new C\Rendering\ExpressionSql())->branches($input));
    }

    public function testBinaryAddsNecessaryRightOperandGrouping(): void
    {
        $layout = new E\Rendering\SqliteBinaryLayout(E\SqliteBinaryOperator::Subtract, '-', '', '', false);
        $inner = new C\Expression\BinaryInput(new E\SqliteInteger(new UnsignedInteger('2')), E\SqliteBinaryOperator::Subtract, new E\SqliteInteger(new UnsignedInteger('3')), $layout);
        $input = new C\Expression\BinaryInput(new E\SqliteInteger(new UnsignedInteger('1')), E\SqliteBinaryOperator::Subtract, $inner, $layout);
        self::assertSame('1-(2-3)', (new C\Rendering\ExpressionSql())->binary($input));
    }
}
