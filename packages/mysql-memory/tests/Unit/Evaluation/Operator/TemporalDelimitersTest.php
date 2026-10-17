<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Operator;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Operator\TemporalDelimiters;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TemporalDelimiters::class)]
#[Small]
final class TemporalDelimitersTest extends TestCase
{
    public function testPositionDistinguishesIncompleteTextFromRecognizedClockSeparators(): void
    {
        $delimiters = new TemporalDelimiters();
        self::assertSame(0, $delimiters->position("\t", false));
        self::assertNull($delimiters->position('x ', false));
        self::assertSame(8, $delimiters->position('12:00:00  ', true));
        self::assertSame(11, $delimiters->position('2001-01-01  12:00:00', true));
        self::assertNull($delimiters->position('2001-01-01 12:00:00', true));
    }

    public function testWarnRetainsTheDelimiterAndRowWithoutRaisingInStrictMode(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0, true);
        $context->row = 3;
        (new TemporalDelimiters())->warn("\t", false, $context);
        $warnings = $session->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['Warning', '4096', "Delimiter '\\t' in position 0 in datetime value '\t' at row 3 is superfluous and is deprecated. Please remove."]], $warnings->rows);
    }
}
