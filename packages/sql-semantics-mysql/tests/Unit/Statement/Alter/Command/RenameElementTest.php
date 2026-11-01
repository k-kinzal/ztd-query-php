<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;

#[CoversClass(RenameElement::class)]
#[Medium]
final class RenameElementTest extends TestCase
{
    public function testDeriveCommandDerivesNothing(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        $alter = $semantics->analyze('ALTER TABLE t RENAME KEY i TO j', [$table]);

        self::assertSame([], $alter->diagnostics());
    }

    public function testRenderWritesTheKind(): void
    {
        self::assertSame('ALTER TABLE t RENAME KEY i TO j', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t RENAME KEY i TO j')->toString());
    }

    public function testRejectsAnIndexKeywordForARenamedColumn(): void
    {
        $this->expectExceptionMessage('Only an altered index has an interchangeable index keyword.');
        new RenameElement(\SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind::Column, new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), keyword: \SqlSemantics\Platform\MySql\Statement\Table\IndexWord::Key);
    }
}
