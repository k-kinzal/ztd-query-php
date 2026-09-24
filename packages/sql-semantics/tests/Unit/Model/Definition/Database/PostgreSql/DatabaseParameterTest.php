<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseParameter;

#[CoversClass(DatabaseParameter::class)]
final class DatabaseParameterTest extends TestCase
{
    #[TestWith(['connection_limit', DatabaseParameter::ConnectionLimit])]
    #[TestWith(['lc_collate', DatabaseParameter::LcCollate])]
    #[TestWith(['owner', DatabaseParameter::Owner])]
    #[TestWith(['OWNER', null])]
    #[TestWith(['connection limit', null])]
    #[TestWith(['oids', null])]
    public function testNamedAcceptsOnlyTheLowerCaseServerSpelling(string $name, ?DatabaseParameter $expected): void
    {
        self::assertSame($expected, DatabaseParameter::named($name));
    }

    public function testAlterableSelectsTheConnectionAndTemplateProperties(): void
    {
        $alterable = array_values(array_filter(DatabaseParameter::cases(), static fn (DatabaseParameter $parameter): bool => $parameter->alterable()));
        self::assertSame([DatabaseParameter::AllowConnections, DatabaseParameter::ConnectionLimit, DatabaseParameter::IsTemplate], $alterable);
    }

    #[TestWith([DatabaseParameter::IsTemplate, true, true])]
    #[TestWith([DatabaseParameter::IsTemplate, 'true', false])]
    #[TestWith([DatabaseParameter::ConnectionLimit, -1, true])]
    #[TestWith([DatabaseParameter::ConnectionLimit, -2, false])]
    #[TestWith([DatabaseParameter::Oid, 16384, true])]
    #[TestWith([DatabaseParameter::Oid, 16383, false])]
    #[TestWith([DatabaseParameter::Encoding, 6, true])]
    #[TestWith([DatabaseParameter::Strategy, 'FILE_COPY', true])]
    #[TestWith([DatabaseParameter::Strategy, 'copy', false])]
    #[TestWith([DatabaseParameter::LocaleProvider, 'icu', true])]
    #[TestWith([DatabaseParameter::LocaleProvider, 'posix', false])]
    #[TestWith([DatabaseParameter::Owner, '', false])]
    #[TestWith([DatabaseParameter::Locale, '', true])]
    #[TestWith([DatabaseParameter::Tablespace, null, true])]
    public function testAcceptsChecksTheDeclaredValueDomain(DatabaseParameter $parameter, string|int|bool|null $value, bool $expected): void
    {
        self::assertSame($expected, $parameter->accepts($value));
    }

    #[TestWith([DatabaseParameter::AllowConnections, false, true])]
    #[TestWith([DatabaseParameter::AllowConnections, 1, false])]
    #[TestWith([DatabaseParameter::ConnectionLimit, 2147483647, true])]
    #[TestWith([DatabaseParameter::ConnectionLimit, 2147483648, false])]
    #[TestWith([DatabaseParameter::Oid, 4294967295, true])]
    #[TestWith([DatabaseParameter::Oid, 4294967296, false])]
    #[TestWith([DatabaseParameter::Encoding, 'UTF8', true])]
    #[TestWith([DatabaseParameter::Encoding, '', false])]
    #[TestWith([DatabaseParameter::Encoding, true, false])]
    #[TestWith([DatabaseParameter::LocaleProvider, 'ICU', true])]
    #[TestWith([DatabaseParameter::Owner, 'alice', true])]
    #[TestWith([DatabaseParameter::Owner, 1, false])]
    #[TestWith([DatabaseParameter::Template, 'template0', true])]
    #[TestWith([DatabaseParameter::Tablespace, 'fast', true])]
    #[TestWith([DatabaseParameter::LcCollate, 'C', true])]
    #[TestWith([DatabaseParameter::LcCtype, 'C', true])]
    #[TestWith([DatabaseParameter::IcuLocale, 'und', true])]
    #[TestWith([DatabaseParameter::IcuRules, '&a < b', true])]
    #[TestWith([DatabaseParameter::BuiltinLocale, 'C.UTF-8', true])]
    #[TestWith([DatabaseParameter::CollationVersion, '1', true])]
    #[TestWith([DatabaseParameter::Location, '/data', true])]
    #[TestWith([DatabaseParameter::Location, 1, false])]
    public function testAcceptsChecksEveryParameterDomain(DatabaseParameter $parameter, string|int|bool|null $value, bool $expected): void
    {
        self::assertSame($expected, $parameter->accepts($value));
    }
}
