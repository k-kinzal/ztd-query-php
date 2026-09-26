<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Schema\Invariant;
use SqlSemantics\Core\Type\TypeName;

#[CoversClass(TypeName::class)]
#[CoversClass(Invariant::class)]
#[Small]
final class TypeNameTest extends TestCase
{
    public function testEqualsComparesEveryPartExactly(): void
    {
        $name = new TypeName(['app', 'money_amount']);
        self::assertTrue($name->equals(new TypeName(['app', 'money_amount'])));
        self::assertFalse($name->equals(new TypeName(['money_amount'])));
        self::assertFalse($name->equals(new TypeName(['app', 'Money_Amount'])));
    }

    public function testQualifiedNameJoinsThePartsForDiagnostics(): void
    {
        self::assertSame('app.money_amount', (new TypeName(['app', 'money_amount']))->qualifiedName());
        self::assertSame('FLOATING POINT', (new TypeName(['FLOATING POINT']))->qualifiedName());
    }
}
