<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Operand::class)]
#[Medium]
final class OperandTest extends TestCase
{
    public function testDeriveScalarRecordsAFactForEveryOperand(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1 + 'a'");
        $expression = $operation->field(0)->expression;

        self::assertInstanceOf(Binary::class, $expression);
        self::assertTrue($expression->hasBinding());
        $left = $expression->left->scalarInformation()->type;
        $right = $expression->right->scalarInformation()->type;
        self::assertInstanceOf(Known::class, $left);
        self::assertInstanceOf(Known::class, $right);
        self::assertSame('INTEGER', $left->descriptor->name());
        self::assertSame('TEXT', $right->descriptor->name());
        self::assertSame(Nullability::NotNull, $expression->scalarInformation()->nullability);
    }

    public function testInitializeScalarInformationPublishesTheOccurrenceMeaning(): void
    {
        $node = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $information = new \SqlSemantics\Statement\Fact\ScalarFact(new Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), Nullability::NotNull);
        $node->initializeScalarInformation($information);
        self::assertSame($information, $node->scalarInformation());
    }

    public function testScalarInformationIsOwnedByTheOccurrence(): void
    {
        $node = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        $this->expectExceptionMessage('A scalar occurrence must be resolved before use.');
        $node->scalarInformation();
    }

    public function testHasScalarInformationDistinguishesAnUnresolvedOccurrence(): void
    {
        $node = new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral('1');
        self::assertFalse($node->hasScalarInformation());
        $node->initializeScalarInformation(new \SqlSemantics\Statement\Fact\ScalarFact(new Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), Nullability::NotNull));
        self::assertTrue($node->hasScalarInformation());
    }
}
