<?php

declare(strict_types=1);

namespace Tests\Unit\Variable;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Session\Variables;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\TimeSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(TimeSettings::class)]
#[Small]
final class TimeSettingsTest extends TestCase
{
    public function testZoneHoldsTheNameOfTheTables(): void
    {
        $instance = new Instance();
        $settings = new TimeSettings(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(['Europe/Paris', '+05:30'], [$settings->zone('europe/paris', Domain::string(12, Collation::known('utf8mb4_0900_ai_ci'))), $settings->zone('+5:30', Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')))]);
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown or incorrect time zone: 'bogus'");
        $settings->zone('bogus', Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')));
    }

    public function testLocaleTakesANameOrANumber(): void
    {
        $instance = new Instance();
        $settings = new TimeSettings(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(['de_DE', 'fr_FR'], [$settings->locale('de_de', Domain::string(5, Collation::known('utf8mb4_0900_ai_ci'))), $settings->locale(5, Domain::integer())]);
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Unknown locale: 'xx'");
        $settings->locale('xx', Domain::string(2, Collation::known('utf8mb4_0900_ai_ci')));
    }

    public function testTimestampRoundsToMicrosecondsButNeverToTheNextSecond(): void
    {
        $instance = new Instance();
        $diagnostics = new Diagnostics();
        $settings = new TimeSettings(new Context(new SqlModes([]), $diagnostics, new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(['1700000000.123457', '1.999999', null, null], [$settings->timestamp('1700000000.123456789', Domain::decimal(19, 9)), $settings->timestamp('1.9999999', Domain::decimal(8, 7)), $settings->timestamp(0, Domain::integer()), $settings->timestamp(-1, Domain::integer())]);
        self::assertSame(1, $diagnostics->count());
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Variable 'timestamp' can't be set to the value of '2147483648'");
        $settings->timestamp(2147483648, Domain::integer());
    }

    public function testShownWritesARefusedValueAsTheServerDoes(): void
    {
        $instance = new Instance();
        $settings = new TimeSettings(new Context(new SqlModes([]), new Diagnostics(), new Variables($instance->catalog, $instance->globals), 0.0));

        self::assertSame(['0.5', '1e300', '9.223372036854776e18'], [$settings->shown('0.5', Domain::decimal(2, 1), '0.5'), $settings->shown(1e300, Domain::double(), '1' . str_repeat('0', 300)), $settings->shown(PHP_INT_MAX, Domain::integer(), '9223372036854775807')]);
    }
}
