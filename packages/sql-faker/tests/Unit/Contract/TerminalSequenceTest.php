<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TerminalSequence;

#[CoversNothing]
final class TerminalSequenceTest extends TestCase
{
    public function testConstructorStoresTerminalList(): void
    {
        self::assertSame(['IDENT'], (new TerminalSequence(['IDENT']))->terminals);
    }

    public function testConstructorRejectsInvalidTerminalEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TerminalSequence(['']);
    }
}
