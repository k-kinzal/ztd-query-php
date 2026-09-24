<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\TypeDeclaration;
use SqlSemantics\Type\Identity\TemporalStorage;
use SqlSemantics\Type\Identity\TimeZoneMode;

#[CoversClass(TimeZoneMode::class)]
#[Medium]
final class TimeZoneModeTest extends TestCase
{
    public function testRepresentsEveryDeclaredInterpretation(): void
    {
        self::assertSame(['', 'WITHOUT TIME ZONE', 'WITH TIME ZONE'], array_column(TimeZoneMode::cases(), 'value'));
    }

    public function testClassifiesTimeZoneClausesAndSelectsTheZonedName(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a TIMESTAMP(3) WITH TIME ZONE, b TIME WITHOUT TIME ZONE, c TIMESTAMP)')->tables[0];
        $modes = array_map(static function ($column): TimeZoneMode {
            self::assertInstanceOf(TemporalStorage::class, $column->type->identity);
            return $column->type->identity->timeZone;
        }, $table->columns);
        self::assertSame([TimeZoneMode::With, TimeZoneMode::Without, TimeZoneMode::Unspecified], $modes);
        self::assertSame(['timestamptz', 'time', 'timestamp'], array_map(static fn ($column): string => $column->type->name, $table->columns));
        self::assertSame('timestamp(3) WITH TIME ZONE', TypeDeclaration::write($table->columns[0]->type)->toString());
    }
}
