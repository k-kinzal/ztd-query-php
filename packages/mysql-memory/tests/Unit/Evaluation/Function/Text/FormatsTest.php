<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Text\Formats;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Formats::class)]
#[Small]
final class FormatsTest extends TestCase
{
    public function testRoutinesNamesFormat(): void
    {
        self::assertSame(['FORMAT'], array_map(static fn ($routine): string => $routine->name, (new Formats())->routines()));
    }

    public function testResolveReadsALiteralLocaleOnceWhenNoRowIsFormatted(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query("SELECT FORMAT(a, 1, 'xx') FROM t");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '1649', "Unknown locale: 'xx'"]], $warnings->rows);
    }

    public function testLocaleNamesAKnownLocaleWithoutRegardToCase(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));

        self::assertSame(['de_DE', 'en_US'], [(new Formats())->locale($frame, new Constant(Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')), 'DE_de')), (new Formats())->locale($frame, new Constant(Domain::string(5, Collation::known('utf8mb4_0900_ai_ci')), 'de-DE'))]);
    }

    public function testFormatConvertsTheCountBeforeResolvingARuntimeLocale(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT FORMAT(USER(),USER(),USER())');

        self::assertSame([
            ['Warning', 1292, "Truncated incorrect INTEGER value: 'root@localhost'"],
            ['Warning', 1649, "Unknown locale: 'root@localhost'"],
            ['Warning', 1292, "Truncated incorrect DOUBLE value: 'root@localhost'"],
        ], $session->diagnostics->conditions);
    }

    public function testFormatSkipsRuntimeLocaleWhenTheCountIsNull(): void
    {
        $session = (new Instance())->connect();
        $session->query('SELECT FORMAT(USER(),NULL,USER())');

        self::assertSame([], $session->diagnostics->conditions);
    }

    public function testFormatRoundsAndGroupsAsTheServer(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT FORMAT(1234567.891, 2), FORMAT(2.5, 0), FORMAT(2.5e0, 0), FORMAT(-1e-10, 3), FORMAT(-0.0001, 2), FORMAT(12.345, 100), FORMAT(1.5, 4294967298), FORMAT(TIME'10:11:12.5', 0), FORMAT(NULL, 1), FORMAT('-1e400', 0) LIKE '-179,769,313,486,231,570,000,%'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1,234,567.89', '3', '2', '-0.000', '0.00', '12.345000000000000000000000000000', '1.50', '101,112', null, '1']], $result->rows);
        self::assertSame(192, $result->columns[0]->length);
    }

    public function testFormatWritesTheSeparatorsOfTheLocale(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT FORMAT(-1234567890123.456, 3, 'en_IN'), FORMAT(1234.5, 1, 'de_DE'), FORMAT(1234.5, 1, 'fr_FR'), FORMAT(1234.5, 1, 'de_CH'), HEX(FORMAT(1234.5, 1, 'bg_BG')), FORMAT(1234.5, 1, NULL)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['-12,34,56,78,90,123.456', '1.234,5', '1234,5', "1'234.5", '31003233342C35', '1,234.5']], $result->rows);
        self::assertSame([['Warning', '1649', "Unknown locale: 'NULL'"]], $warnings->rows);
    }

    public function testPlacesTakesTheCountAsA32BitInteger(): void
    {
        self::assertSame([0, 30, 2, 0], [(new Formats())->places(-1), (new Formats())->places(31), (new Formats())->places(4294967298), (new Formats())->places(PHP_INT_MAX)]);
    }

    public function testWrittenGroupsTheIntegerDigits(): void
    {
        self::assertSame(['1,234,567.5', '12,34,567', '1234567,5'], [(new Formats())->written('1234567.5', 'en_US'), (new Formats())->written('1234567', 'en_IN'), (new Formats())->written('1234567.5', 'it_IT')]);
    }
}
