<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;

#[CoversClass(ColumnDomain::class)]
#[Small]
final class ColumnDomainTest extends TestCase
{
    public function testNameReportsAStandardTypeInUpperCase(): void
    {
        $domain = new ColumnDomain('integer');

        self::assertSame('INTEGER', $domain->name());
        self::assertSame('INTEGER', $domain->declared);
        self::assertTrue($domain->standard);
    }

    public function testNameKeepsAnyOtherTypeAsDeclared(): void
    {
        $domain = new ColumnDomain('Varchar(10)');

        self::assertSame('Varchar(10)', $domain->name());
        self::assertFalse($domain->standard);
    }

    public function testNameKeepsATextThatSqliteDoesNotCompareWithTheStandardNames(): void
    {
        $domain = new ColumnDomain('integer', false, false);

        self::assertSame('integer', $domain->name());
        self::assertFalse($domain->standard);
        self::assertSame(Affinity::Integer, $domain->affinity);
    }

    public function testAffinityFollowsTheOrderedSubstringRules(): void
    {
        self::assertSame(Affinity::Integer, (new ColumnDomain('TINYINT'))->affinity);
        self::assertSame(Affinity::Integer, (new ColumnDomain('CHARINT'))->affinity);
        self::assertSame(Affinity::Text, (new ColumnDomain('NCHAR(55)'))->affinity);
        self::assertSame(Affinity::Text, (new ColumnDomain('CLOB'))->affinity);
        self::assertSame(Affinity::Text, (new ColumnDomain('text blob'))->affinity);
        self::assertSame(Affinity::Blob, (new ColumnDomain('BLOB'))->affinity);
        self::assertSame(Affinity::Blob, (new ColumnDomain(''))->affinity);
        self::assertSame(Affinity::Real, (new ColumnDomain('DOUBLE PRECISION'))->affinity);
        self::assertSame(Affinity::Real, (new ColumnDomain('float'))->affinity);
        self::assertSame(Affinity::Numeric, (new ColumnDomain('DECIMAL(10,5)'))->affinity);
        self::assertSame(Affinity::Numeric, (new ColumnDomain('STRING'))->affinity);
        self::assertSame(Affinity::Numeric, (new ColumnDomain('ANY'))->affinity);
    }

    public function testAffinityOfAnEmptyDeclaredTextIsNumeric(): void
    {
        self::assertSame(Affinity::Numeric, (new ColumnDomain('', false, false))->affinity);
    }

    public function testAffinityOfAStrictAnyColumnIsBlob(): void
    {
        $domain = new ColumnDomain('any', true);

        self::assertTrue($domain->strict);
        self::assertSame('ANY', $domain->declared);
        self::assertSame(Affinity::Blob, $domain->affinity);
    }

    public function testTypedTellsNoDeclaredTypeFromAnEmptyOne(): void
    {
        self::assertFalse((new ColumnDomain(''))->typed());
        self::assertFalse((new ColumnDomain('', true))->typed());
        self::assertTrue((new ColumnDomain('', false, false))->typed());
        self::assertTrue((new ColumnDomain('BLOB'))->typed());
        self::assertTrue((new ColumnDomain('ANY', true))->typed());
    }

    public function testRowidCapableHoldsOnlyForTheStandardNameInteger(): void
    {
        self::assertTrue((new ColumnDomain('Integer'))->rowidCapable());
        self::assertFalse((new ColumnDomain('INT'))->rowidCapable());
        self::assertFalse((new ColumnDomain('INTEGER(8)'))->rowidCapable());
        self::assertFalse((new ColumnDomain('integer', false, false))->rowidCapable());
    }
}
