<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(Statement::class)]
#[Medium]
final class StatementTest extends TestCase
{
    public function testDeriveStatementRecordsTheRowsAndDeclarationsOfTheRoot(): void
    {
        $semantics = new Inspection();
        $statement = $semantics->inspect('CREATE TABLE t (a INTEGER)')->statement;
        $derivation = new Derivation($semantics->context([]));

        $statement->deriveStatement($derivation);

        self::assertCount(1, $derivation->facts()->declarations);
        self::assertNull($derivation->facts()->output);
    }
}
