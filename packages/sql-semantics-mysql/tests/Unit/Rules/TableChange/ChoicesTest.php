<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableChange\Choices;
use SqlSemantics\Statement\Fact\Diagnostic;

#[CoversClass(Choices::class)]
#[Medium]
final class ChoicesTest extends TestCase
{
    public function testAlgorithmChecksTheAlgorithm(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame(['SLOW is not an ALGORITHM the server knows.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ALGORITHM = COPY, ALGORITHM = SLOW')->facts->diagnostics));
    }

    public function testLockChecksTheLock(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame(['FULL is not a LOCK the server knows.'], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t LOCK = none, LOCK = FULL')->facts->diagnostics));
    }

    public function testLegacyAcceptsDefaultAsANameIn57(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-5.7.44');

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ALGORITHM = `DEFAULT`, LOCK = `DEFAULT`')->facts->diagnostics));
    }

    public function testCheckIgnoresTheKeywordDefault(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertSame([], array_map(static fn (Diagnostic $diagnostic): string => $diagnostic->message(), $semantics->analyze('ALTER TABLE t ALGORITHM = DEFAULT, LOCK = DEFAULT')->facts->diagnostics));
    }
}
