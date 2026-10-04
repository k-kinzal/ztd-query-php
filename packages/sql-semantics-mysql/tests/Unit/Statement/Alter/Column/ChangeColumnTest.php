<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\ChangeColumn;

#[CoversClass(ChangeColumn::class)]
#[Medium]
final class ChangeColumnTest extends TestCase
{
    public function testChangedAnswersTheRedefinedColumn(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t MODIFY a BIGINT, CHANGE b c INT');

        self::assertInstanceOf(AlterTable::class, $alter->statement);
        [$modify, $change] = $alter->statement->commands;
        self::assertInstanceOf(ChangeColumn::class, $modify);
        self::assertInstanceOf(ChangeColumn::class, $change);
        self::assertSame(['a', 'b'], [$modify->changed()->column->value, $change->changed()->column->value]);
    }

    public function testDeriveCommandDerivesTheDefinition(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t MODIFY a INT DEFAULT (x)', [$table]);

        self::assertSame('Column x does not exist.', $alter->facts->diagnostics[0]->message());
    }

    public function testRenderWritesChangeOrModify(): void
    {
        self::assertSame('ALTER TABLE t CHANGE COLUMN a b INT FIRST, MODIFY COLUMN c TEXT', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t CHANGE a b INT FIRST, MODIFY COLUMN c TEXT')->toString());
    }
}
