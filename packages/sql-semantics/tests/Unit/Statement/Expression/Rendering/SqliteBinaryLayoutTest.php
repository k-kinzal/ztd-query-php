<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression as E;

#[CoversClass(E\Rendering\SqliteBinaryLayout::class)]
#[Small]
final class SqliteBinaryLayoutTest extends TestCase
{
    public function testSymbolRetainsOnlyTheChosenOperatorAndWhitespace(): void
    {
        $layout = new E\Rendering\SqliteBinaryLayout(E\SqliteBinaryOperator::IsNot, 'is  not', "\t", ' ');
        self::assertSame("\tis  not ", $layout->symbol());
    }

    public function testBetweenDoesNotCreateACommentFromAdjacentMinusSigns(): void
    {
        $layout = new E\Rendering\SqliteBinaryLayout(E\SqliteBinaryOperator::Subtract, '-', '', '', false);
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $layout->between('1', '-2'));
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(3, $result->fetchColumn());
        self::assertSame('1- -2', $layout->between('1', '-2'));
    }

    public function testRejectsADifferentOperationInTheSpelling(): void
    {
        $this->expectException(\SqlSemantics\Statement\Validation\Failure\InvalidConstruction::class);
        new E\Rendering\SqliteBinaryLayout(E\SqliteBinaryOperator::Add, '-');
    }

    public function testRejectsAnOperandHiddenInAnOperatorGap(): void
    {
        $this->expectException(\SqlSemantics\Statement\Validation\Failure\InvalidConstruction::class);
        new E\Rendering\SqliteBinaryLayout(E\SqliteBinaryOperator::Add, '+', ' OR 1 ');
    }
}
