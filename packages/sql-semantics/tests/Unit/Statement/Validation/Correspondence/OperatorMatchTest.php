<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation\Correspondence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Construction as C;
use SqlSemantics\Statement\Expression as E;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;
use SqlSemantics\Statement\Validation\Correspondence as V;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(V\OperatorMatch::class)]
#[Small]
final class OperatorMatchTest extends TestCase
{
    public function testChildrenRejectsAChangedBinaryOperation(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $value = new E\NullConstant();
        $input = new C\Expression\BinaryInput($value, E\SqliteBinaryOperator::Add, $value);
        $actual = new E\SqliteBinary($value, E\SqliteBinaryOperator::Subtract, $value);
        $this->expectException(InvariantViolation::class);
        (new V\OperatorMatch())->children(new V\ScalarPair($input, $actual, $scope));
    }

    public function testChildrenKeepsDistinctLeftAndRightOperandRoles(): void
    {
        $scope = new Scope(new Catalog(new SearchPath(new Name('main'))));
        $left = new E\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('1'));
        $right = new E\SqliteInteger(new \SqlSemantics\Statement\Literal\UnsignedInteger('2'));
        $input = new C\Expression\BinaryInput($left, E\SqliteBinaryOperator::Subtract, $right);
        $actual = new E\SqliteBinary($left, E\SqliteBinaryOperator::Subtract, $right);
        $children = (new V\OperatorMatch())->children(new V\ScalarPair($input, $actual, $scope));
        self::assertSame($left, $children[0]->actual);
        self::assertSame($right, $children[1]->actual);
    }
}
