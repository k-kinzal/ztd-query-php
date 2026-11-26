<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertRows;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\Update;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ConflictResolution::class)]
#[Medium]
final class ConflictResolutionTest extends TestCase
{
    public function testCasesSpellTheFiveAlgorithms(): void
    {
        self::assertSame(['ROLLBACK', 'ABORT', 'FAIL', 'IGNORE', 'REPLACE'], array_map(static fn (ConflictResolution $resolution): string => $resolution->value, ConflictResolution::cases()));
        self::assertSame(ConflictResolution::Ignore, ConflictResolution::from('IGNORE'));
    }

    public function testCasesAreReadAfterOrInInsertAndUpdate(): void
    {
        $semantics = new Inspection();
        $insert = $semantics->inspect('insert or replace into t values (1)');
        $update = $semantics->inspect('update or fail t set a = 1');

        self::assertInstanceOf(InsertRows::class, $insert->statement);
        self::assertSame(ConflictResolution::Replace, $insert->statement->into->resolution);
        self::assertFalse($insert->statement->into->replace);
        self::assertSame('INSERT OR REPLACE INTO t VALUES (1)', $insert->toString());
        self::assertInstanceOf(Update::class, $update->statement);
        self::assertSame(ConflictResolution::Fail, $update->statement->resolution);
        self::assertSame('UPDATE OR FAIL t SET a = 1', $update->toString());
    }
}
