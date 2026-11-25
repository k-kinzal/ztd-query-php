<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\ForeignKey\Add\Enumeration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyIntegerDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyScalarStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Domain\ForeignKeyTextCollation;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationDomain;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationMember;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Enumeration\ForeignKeyEnumerationStorage;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;

#[CoversClass(ForeignKeyEnumerationDomain::class)]
final class ForeignKeyEnumerationDomainTest extends TestCase
{
    public function testChildKeyBytesRetainsAOneByteOrdinalAcrossDifferentCollations(): void
    {
        $member = new ForeignKeyEnumerationMember('ready');
        $left = new ForeignKeyEnumerationStorage(ForeignKeyEnumerationKind::Enum, [$member], ForeignKeyTextCollation::Binary);
        $right = new ForeignKeyEnumerationStorage(ForeignKeyEnumerationKind::Set, [$member], ForeignKeyTextCollation::Unicode);
        $pair = new ForeignKeyEnumerationDomain($left, $right);

        self::assertSame(1, $pair->childKeyBytes());
        self::assertSame($left, $pair->child);
        self::assertSame($right, $pair->parent);
    }

    public function testUnsignedBytesDistinguishesYearAndOrdinarySignedIntegers(): void
    {
        $member = new ForeignKeyEnumerationMember('ready');
        $field = new ForeignKeyEnumerationStorage(ForeignKeyEnumerationKind::Enum, [$member], ForeignKeyTextCollation::Binary);
        $year = new ForeignKeyScalarStorage(ForeignKeyScalarKind::Year);
        $domain = new ForeignKeyEnumerationDomain($field, $year);

        self::assertSame(1, $domain->unsignedBytes($year));
        self::assertSame(1, $domain->unsignedBytes($field));
        self::assertNull($domain->unsignedBytes(new ForeignKeyIntegerDomain(IntegerStorage::Tiny, false)));
        self::assertSame(2, $domain->unsignedBytes(new ForeignKeyIntegerDomain(IntegerStorage::Small, true)));
    }

    public function testCheckReleaseChecksBothNativeSides(): void
    {
        $member = new ForeignKeyEnumerationMember("space\0data");
        $field = new ForeignKeyEnumerationStorage(ForeignKeyEnumerationKind::Set, [$member], ForeignKeyTextCollation::Binary);
        $domain = new ForeignKeyEnumerationDomain(new ForeignKeyIntegerDomain(IntegerStorage::Tiny, true), $field);
        $domain->checkRelease(false);

        self::assertSame($field, $domain->parent);
    }
}
