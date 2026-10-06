<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Statement\Shape\Field;

#[CoversClass(UnaryOperator::class)]
#[Medium]
final class UnaryOperatorTest extends TestCase
{
    public function testEachOperatorIsReadFromItsSpelling(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT NOT 1, ~1, -1, +1');
        $operators = array_map(static fn (Field $field): ?UnaryOperator => $field->expression instanceof Unary ? $field->expression->operator : null, $query->fields()->items ?? []);

        self::assertSame([UnaryOperator::Not, UnaryOperator::BitNot, UnaryOperator::Minus, UnaryOperator::Plus], $operators);
    }

    public function testEachOperatorCarriesItsSpelling(): void
    {
        self::assertSame(['NOT', '~', '+', '-'], array_map(static fn (UnaryOperator $operator): string => $operator->value, UnaryOperator::cases()));
    }
}
