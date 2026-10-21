<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse;

use MySqlMemory\Instance;
use MySqlMemory\Session\Parse\SpatialWarnings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpatialWarnings::class)]
#[Small]
final class SpatialWarningsTest extends TestCase
{
    public function testAcceptedDefersWarningsUntilArgumentCountsHavePassed(): void
    {
        $semantics = (new Instance(version: '5.7.44'))->connect()->semantics();
        $valid = $semantics->analyze('SELECT DIMENSION(SRID(NULL))');
        self::assertCount(2, (new SpatialWarnings())->accepted($valid, $valid->facts->warnings));
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage("Incorrect parameter count in the call to native function 'SRID'");

        $semantics->analyze('SELECT SRID(DIMENSION(NULL), 1)');
    }

    public function testBeforeKeepsOnlyCompleteUnqualifiedCallsBeforeTheError(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $sql = 'SELECT SRID(Point(1,2)), db.SRID(NULL), SRID(1,2), DIMENSION(NULL) FROM';
        $tokens = $session->semantics()->parser()->tokenize($sql);
        $warnings = (new SpatialWarnings())->before($tokens, $sql, strlen($sql), $session);

        self::assertCount(2, $warnings);
        self::assertSame([1287, 1287], array_map(static fn ($warning): int => $warning->getCode(), array_values($warnings)));
        self::assertSame([], (new SpatialWarnings())->before($tokens, $sql, 15, $session));
        self::assertSame([], (new SpatialWarnings())->before($tokens, $sql, strlen($sql), (new Instance('8.4.7'))->connect()));
    }

    public function testCompleteRequiresAParsedOneArgumentFunction(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $reader = new SpatialWarnings();

        self::assertTrue($reader->complete('SRID(Point(1,2))', $session));
        self::assertFalse($reader->complete('GEOMETRYN(NULL,1)', $session));
        self::assertFalse($reader->complete('SRID(', $session));
        self::assertFalse($reader->complete('SRID(1,2)', $session));
        self::assertFalse($reader->complete('db.SRID(NULL)', $session));
    }
}
