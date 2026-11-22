<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(AlgorithmOption::class)]
#[Medium]
final class AlgorithmOptionTest extends TestCase
{
    public function testDeriveOptionReportsAnAlgorithmTheReleaseDoesNotKnow(): void
    {
        self::assertSame('INSTANT is not an ALGORITHM the server knows.', (new Inspection('mysql-5.7.44'))->diagnose('DROP INDEX i ON t ALGORITHM = INSTANT')->facts->diagnostics[0]->message());
        self::assertSame([], (new Inspection())->diagnose('DROP INDEX i ON t ALGORITHM = instant')->facts->diagnostics);
    }

    public function testDeriveCommandChecksTheAlgorithm(): void
    {
        self::assertSame('NOCOPY is not an ALGORITHM the server knows.', (new Inspection())->diagnose('ALTER TABLE t ALGORITHM = NOCOPY')->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheAlgorithmOrDefault(): void
    {
        self::assertSame('ALTER TABLE t ALGORITHM = DEFAULT, ALGORITHM = INPLACE', (new Inspection())->diagnose('ALTER TABLE t ALGORITHM DEFAULT, ALGORITHM INPLACE')->toString());
    }
}
