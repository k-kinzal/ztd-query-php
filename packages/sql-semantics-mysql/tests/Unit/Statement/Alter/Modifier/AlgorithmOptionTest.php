<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Modifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;

#[CoversClass(AlgorithmOption::class)]
#[Medium]
final class AlgorithmOptionTest extends TestCase
{
    public function testDeriveOptionAcceptsTheKnownAlgorithm(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('DROP INDEX i ON t ALGORITHM = COPY')->diagnostics());
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('DROP INDEX i ON t ALGORITHM = instant')->diagnostics());
    }

    public function testDeriveCommandChecksTheAlgorithm(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM = INPLACE')->diagnostics());
    }

    public function testRenderWritesTheAlgorithmOrDefault(): void
    {
        self::assertSame('ALTER TABLE t ALGORITHM = DEFAULT, ALGORITHM = INPLACE', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALGORITHM DEFAULT, ALGORITHM INPLACE')->toString());
    }
}
