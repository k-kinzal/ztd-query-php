<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind as Kind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;

#[CoversClass(ForeignKeyScalarDomain::class)]
final class ForeignKeyScalarDomainTest extends TestCase
{
    public function testChildKeyBytesKeepsTheDifferentEncodingsInTheirOwnRoles(): void
    {
        $time = new ForeignKeyScalarStorage(Kind::Time, 4);
        $decimal = new ForeignKeyScalarStorage(Kind::Decimal, 10, 2);
        $pair = new ForeignKeyScalarDomain($time, $decimal);

        self::assertSame($time, $pair->child);
        self::assertSame($decimal, $pair->parent);
        self::assertSame(5, $pair->childKeyBytes());
    }

    public function testChildKeyBytesUsesTheSelectedIntegralReferencingField(): void
    {
        $year = new ForeignKeyScalarStorage(Kind::Year);
        $tiny = new ForeignKeyIntegerDomain(IntegerStorage::Tiny, true);

        self::assertSame(1, (new ForeignKeyScalarDomain($tiny, $year))->childKeyBytes());
    }
}
