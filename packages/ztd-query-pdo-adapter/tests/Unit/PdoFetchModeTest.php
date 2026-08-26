<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoFetchMode;

#[CoversClass(PdoFetchMode::class)]
final class PdoFetchModeTest extends TestCase
{
    public function testItRemembersTheModeItWasBuiltWith(): void
    {
        self::assertSame(PDO::FETCH_ASSOC, (new PdoFetchMode(PDO::FETCH_ASSOC))->mode);
    }

    public function testItRemembersTheRestOfWhatTheModeReads(): void
    {
        $fetchMode = new PdoFetchMode(PDO::FETCH_CLASS, ['Some\\Row', ['argument']]);

        self::assertSame(['Some\\Row', ['argument']], $fetchMode->arguments);
    }

    public function testAModeSetWithNothingElseRemembersNothingElse(): void
    {
        self::assertSame([], (new PdoFetchMode(PDO::FETCH_NUM))->arguments);
    }
}
