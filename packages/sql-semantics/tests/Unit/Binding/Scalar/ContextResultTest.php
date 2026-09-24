<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Scalar\ContextResult;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\ContextValueKind;
use SqlSemantics\Type\Identity\BuiltinIdentity;

#[CoversClass(ContextResult::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ContextResultTest extends TestCase
{
    #[TestWith([ContextValueKind::CurrentDate, Dialect::PostgreSql, BuiltinIdentity::Date])]
    #[TestWith([ContextValueKind::CurrentTime, Dialect::PostgreSql, BuiltinIdentity::Timetz])]
    #[TestWith([ContextValueKind::CurrentTime, Dialect::MySql, BuiltinIdentity::Time])]
    #[TestWith([ContextValueKind::CurrentTimestamp, Dialect::PostgreSql, BuiltinIdentity::Timestamptz])]
    #[TestWith([ContextValueKind::CurrentTimestamp, Dialect::Sqlite, BuiltinIdentity::Timestamp])]
    #[TestWith([ContextValueKind::LocalTime, Dialect::PostgreSql, BuiltinIdentity::Time])]
    #[TestWith([ContextValueKind::LocalTimestamp, Dialect::PostgreSql, BuiltinIdentity::Timestamp])]
    #[TestWith([ContextValueKind::CurrentUser, Dialect::MySql, BuiltinIdentity::Text])]
    #[TestWith([ContextValueKind::CurrentSchema, Dialect::PostgreSql, BuiltinIdentity::Text])]
    #[TestWith([ContextValueKind::UtcDate, Dialect::MySql, BuiltinIdentity::Date])]
    #[TestWith([ContextValueKind::UtcTime, Dialect::MySql, BuiltinIdentity::Time])]
    #[TestWith([ContextValueKind::UtcTimestamp, Dialect::MySql, BuiltinIdentity::Datetime])]
    #[TestWith([ContextValueKind::StatementTime, Dialect::PostgreSql, BuiltinIdentity::Datetime])]
    #[TestWith([ContextValueKind::SessionUser, Dialect::PostgreSql, BuiltinIdentity::Text])]
    #[TestWith([ContextValueKind::SystemUser, Dialect::MySql, BuiltinIdentity::Text])]
    #[TestWith([ContextValueKind::User, Dialect::MySql, BuiltinIdentity::Text])]
    #[TestWith([ContextValueKind::CurrentRole, Dialect::PostgreSql, BuiltinIdentity::Text])]
    #[TestWith([ContextValueKind::CurrentCatalog, Dialect::PostgreSql, BuiltinIdentity::Text])]
    public function testTypeDescribesEachRequestWithoutPrecision(ContextValueKind $request, Dialect $dialect, BuiltinIdentity $identity): void
    {
        $type = ContextResult::type($request, $dialect, null);
        self::assertSame($identity, $type->identity);
        self::assertSame($dialect, $type->dialect);
    }

    public function testTypeAppliesPrecisionAsTemporalStorageKeepingTheZone(): void
    {
        $withZone = ContextResult::type(ContextValueKind::CurrentTime, Dialect::PostgreSql, 3);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\TemporalStorage::class, $withZone->identity);
        self::assertSame(BuiltinIdentity::Time, $withZone->identity->base);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\Numeric\NumericParameter::class, $withZone->identity->precision);
        self::assertSame('3', $withZone->identity->precision->spelling);
        self::assertSame(\SqlSemantics\Type\Identity\TimeZoneMode::With, $withZone->identity->timeZone);
        self::assertSame('timetz', $withZone->name);
        $local = ContextResult::type(ContextValueKind::LocalTimestamp, Dialect::PostgreSql, 6);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\TemporalStorage::class, $local->identity);
        self::assertSame(BuiltinIdentity::Timestamp, $local->identity->base);
        self::assertSame(\SqlSemantics\Type\Identity\TimeZoneMode::Unspecified, $local->identity->timeZone);
        self::assertSame('timestamp', $local->name);
    }

    #[TestWith([ContextValueKind::CurrentTimestamp, BuiltinIdentity::Timestamp, true])]
    #[TestWith([ContextValueKind::LocalTime, BuiltinIdentity::Time, false])]
    #[TestWith([ContextValueKind::StatementTime, BuiltinIdentity::Datetime, false])]
    public function testTypeStoresThePrecisionOnEveryBase(ContextValueKind $request, BuiltinIdentity $base, bool $withZone): void
    {
        $type = ContextResult::type($request, Dialect::PostgreSql, 2);
        self::assertInstanceOf(\SqlSemantics\Type\Identity\TemporalStorage::class, $type->identity);
        self::assertSame([$base, $withZone], [$type->identity->base, $type->identity->timeZone === \SqlSemantics\Type\Identity\TimeZoneMode::With]);
    }

    #[TestWith([ContextValueKind::CurrentUser])]
    #[TestWith([ContextValueKind::CurrentDate])]
    public function testTypeRejectsAPrecisionOnADateOrText(ContextValueKind $request): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        ContextResult::type($request, Dialect::PostgreSql, 2);
    }
}
