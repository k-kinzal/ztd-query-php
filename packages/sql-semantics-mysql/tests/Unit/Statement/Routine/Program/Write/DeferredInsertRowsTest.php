<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows;

#[CoversClass(DeferredInsertRows::class)]
#[Medium]
final class DeferredInsertRowsTest extends TestCase
{
    public function testRenderKeepsTheWholeWriteAndItsRowRoles(): void
    {
        $sql = 'CREATE PROCEDURE p() INSERT IGNORE INTO d.t (a, b) VALUES (DEFAULT, 7), (DEFAULT, WEIGHT_STRING(ROW(1, 2))) AS fresh ON DUPLICATE KEY UPDATE a = 9';
        $operation = (new Semantics(Dialect::MySql))->analyze($sql);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        self::assertCount(2, $operation->statement->body->rows);
        self::assertCount(1, $operation->statement->body->onDuplicate);
        self::assertSame($sql, $operation->toString());
    }

    public function testDeriveProgramOpensTheDestinationBeforeNestedValueTables(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO d.t VALUES (WEIGHT_STRING(ROW((TABLE d.other), 2)))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        $derivation = new \SqlSemantics\Construction\Derivation((new Semantics(Dialect::MySql))->context([], true));
        $operation->statement->body->deriveProgram($derivation, new \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope($derivation->environment()));
        $tables = array_values(array_filter($derivation->facts()->diagnostics, static fn ($problem): bool => $problem instanceof \SqlSemantics\Statement\Reference\Table\MissingTable));
        self::assertSame(['t', 'other'], array_map(static fn ($problem): string => $problem->name->name->value, $tables));
    }

    public function testProblemRetainsTheValueConstraintInItsWriteEnvelope(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO d.t VALUES (ROW(1, 2))');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredInsertRows::class, $operation->statement->body);
        self::assertSame('Operand should contain 1 column(s), not 2.', $operation->statement->body->problem()->message());
    }
}
