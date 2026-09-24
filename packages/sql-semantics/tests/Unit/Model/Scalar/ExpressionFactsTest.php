<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ExpressionFacts::class)]
final class ExpressionFactsTest extends TestCase
{
    public function testKeepsTheTypeNullabilityAndNullExtensionCauses(): void
    {
        $type = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $facts = new ExpressionFacts($type, Nullability::MaybeNull, ['j0', 'j1']);
        self::assertSame($type, $facts->type);
        self::assertSame(Nullability::MaybeNull, $facts->nullability);
        self::assertSame(['j0', 'j1'], $facts->nullExtendedBy);
    }

    public function testDefaultsToNoNullExtension(): void
    {
        $facts = new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'text'), Nullability::NotNull);
        self::assertSame([], $facts->nullExtendedBy);
    }
}
