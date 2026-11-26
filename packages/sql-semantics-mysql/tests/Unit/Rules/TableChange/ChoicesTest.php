<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Rules\TableChange\Choices;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Choices::class)]
#[Medium]
final class ChoicesTest extends TestCase
{
    public function testAlgorithmChecksTheAlgorithm(): void
    {
        $semantics = new Inspection();

        self::assertSame(['SLOW is not an ALGORITHM the server knows.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER TABLE t ALGORITHM = COPY, ALGORITHM = SLOW')->facts->diagnostics));
    }

    public function testLockChecksTheLock(): void
    {
        $semantics = new Inspection();

        self::assertSame(['FULL is not a LOCK the server knows.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER TABLE t LOCK = none, LOCK = FULL')->facts->diagnostics));
    }

    public function testLegacyAcceptsDefaultAsANameIn57(): void
    {
        $semantics = new Inspection('mysql-5.7.44');

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER TABLE t ALGORITHM = `DEFAULT`, LOCK = `DEFAULT`')->facts->diagnostics));
    }

    public function testCheckIgnoresTheKeywordDefault(): void
    {
        $semantics = new Inspection();

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->diagnose('ALTER TABLE t ALGORITHM = DEFAULT, LOCK = DEFAULT')->facts->diagnostics));
    }
}
