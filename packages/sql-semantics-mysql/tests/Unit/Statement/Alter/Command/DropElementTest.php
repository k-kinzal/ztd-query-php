<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;

#[CoversClass(DropElement::class)]
#[Medium]
final class DropElementTest extends TestCase
{
    public function testRejectsAColumnKeywordChoiceForAnotherElementKind(): void
    {
        $this->expectExceptionMessage('Only a dropped column has an optional COLUMN keyword.');
        new DropElement(\SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind::PrimaryKey, columnWritten: false);
    }

    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t DROP INDEX i, DROP FOREIGN KEY f', [$table]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $alter);

        self::assertSame([], $alter->diagnostics());
    }

    public function testRenderWritesEveryKind(): void
    {
        self::assertSame('ALTER TABLE t DROP a CASCADE, DROP PRIMARY KEY, DROP KEY i, DROP CHECK c, DROP CONSTRAINT d', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t DROP a CASCADE, DROP PRIMARY KEY, DROP KEY i, DROP CHECK c, DROP CONSTRAINT d')->toString());
    }

    public function testRejectsAnIndexKeywordForADroppedColumn(): void
    {
        $this->expectExceptionMessage('Only an altered index has an interchangeable index keyword.');
        new DropElement(\SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind::Column, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), keyword: \SqlSemantics\Platform\MySql\Statement\Table\IndexWord::Key);
    }
}
