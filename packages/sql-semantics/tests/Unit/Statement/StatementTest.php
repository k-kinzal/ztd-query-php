<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Statement\Statement;

#[CoversClass(Statement::class)]
#[Medium]
final class StatementTest extends TestCase
{
    public function testDeriveStatementRecordsTheRowsAndDeclarationsOfTheRoot(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $statement = $semantics->analyze('CREATE TABLE t (a INTEGER)')->statement;
        $derivation = new Derivation($semantics->context([]));

        $statement->deriveStatement($derivation);

        self::assertCount(1, $derivation->facts()->declarations);
        self::assertNull($derivation->facts()->output);
    }
}
