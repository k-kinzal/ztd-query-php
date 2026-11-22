<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\Field;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(UnaryOperator::class)]
#[Medium]
final class UnaryOperatorTest extends TestCase
{
    public function testEachOperatorIsReadFromItsSpelling(): void
    {
        $query = (new Inspection())->inspect('SELECT NOT 1, ~1, -1, +1');
        $operators = array_map(static fn (Field $field): ?UnaryOperator => $field->expression instanceof Unary ? $field->expression->operator : null, $query->fields()->items ?? []);

        self::assertSame([UnaryOperator::Not, UnaryOperator::BitNot, UnaryOperator::Minus, UnaryOperator::Plus], $operators);
    }

    public function testEachOperatorCarriesItsSpelling(): void
    {
        self::assertSame(['NOT', '~', '+', '-'], array_map(static fn (UnaryOperator $operator): string => $operator->value, UnaryOperator::cases()));
    }
}
