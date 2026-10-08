<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Server;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Server\Performance;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(Performance::class)]
#[Small]
final class PerformanceTest extends TestCase
{
    public function testRoutinesNameThePerformanceSchemaFunctions(): void
    {
        self::assertSame(['FORMAT_BYTES', 'FORMAT_PICO_TIME', 'PS_CURRENT_THREAD_ID', 'PS_THREAD_ID'], array_map(static fn ($routine): string => $routine->name, (new Performance())->routines()));
    }

    public function testRoutinesFormatBytesAsTheServerDoes(): void
    {
        $result = (new Instance())->connect()->query('SELECT FORMAT_BYTES(0), FORMAT_BYTES(1023.996), FORMAT_BYTES(1152), FORMAT_BYTES(1048575), FORMAT_BYTES(-9999), FORMAT_BYTES(POW(1024, 6) * 99999.996), FORMAT_BYTES(1e30), FORMAT_BYTES(-0.4), FORMAT_BYTES(NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['   0 bytes', '1023 bytes', '1.12 KiB', '1024.00 KiB', '-9.76 KiB', '100000.00 EiB', '8.67e+11 EiB', '   0 bytes', null]], $result->rows);
        self::assertSame(11, $result->columns[0]->length / 4);
    }

    public function testRoutinesFormatPicosecondsAsTheServerDoes(): void
    {
        $result = (new Instance())->connect()->query('SELECT FORMAT_PICO_TIME(1), FORMAT_PICO_TIME(999999), FORMAT_PICO_TIME(59.999e12), FORMAT_PICO_TIME(3599e12), FORMAT_PICO_TIME(86399e12), FORMAT_PICO_TIME(1e5 * 86400e12), FORMAT_PICO_TIME(-1e308)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['  1 ps', '1000.00 ns', '60.00 s', '59.98 min', '24.00 h', '1.00e+05 d', '-1.16e+291 d']], $result->rows);
    }

    public function testFormatReadsAStringAsADouble(): void
    {
        $context = new Context(new SqlModes([]), new Diagnostics(), (new Instance())->connect()->variables, 0.0);
        $text = (new Performance())->format(new Frame($context), new Constant(Domain::string(5, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci')), '2048x'), Performance::BYTES, '%4d bytes');

        self::assertSame(['2.00 KiB', 1], [$text, $context->diagnostics->count()]);
    }

    public function testThreadAnswersTheThreadIdOfAConnectedSession(): void
    {
        $instance = new Instance();
        $first = $instance->connect();
        $second = $instance->connect();
        $second->close();
        $result = $first->query("SELECT PS_CURRENT_THREAD_ID(), PS_THREAD_ID(1), PS_THREAD_ID(2), PS_THREAD_ID(1.4), PS_THREAD_ID(NULL), PS_THREAD_ID('a'), PS_THREAD_ID(99999999999999999999)")[0];
        $warnings = $first->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['38', '38', null, '38', null, null, null]], $result->rows);
        self::assertSame([['Warning', '1292', "Truncated incorrect INTEGER value: 'a'"], ['Warning', '1292', "Truncated incorrect DECIMAL value: '99999999999999999999'"]], $warnings->rows);
    }

    public function testOffsetAnswersTheThreadsAServerStartsFirst(): void
    {
        self::assertSame([40, 37, 36], [(new Performance())->offset(GrammarRelease::MySql8044), (new Performance())->offset(GrammarRelease::MySql847), (new Performance())->offset(GrammarRelease::MySql910)]);
    }
}
