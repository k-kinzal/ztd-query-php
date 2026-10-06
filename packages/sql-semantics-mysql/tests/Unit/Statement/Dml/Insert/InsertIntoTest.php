<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(InsertInto::class)]
#[Medium]
final class InsertIntoTest extends TestCase
{
    public function testRenderWritesVerbModifiersAndTable(): void
    {
        self::assertSame('INSERT HIGH_PRIORITY IGNORE INTO t PARTITION (p) (a) VALUES (1)', (new Semantics(Dialect::MySql))->analyze('insert high_priority ignore t partition (p) (a) values (1)')->toString());
        self::assertSame('REPLACE DELAYED INTO t VALUES (1)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('replace delayed t value (1)')->toString());
    }

    public function testRenderRejectsReplaceWithIgnore(): void
    {
        $this->expectExceptionMessage('REPLACE takes neither IGNORE nor HIGH_PRIORITY.');

        new InsertInto(true, null, true, new WriteTarget(new QualifiedName(new Name('t'))));
    }

    public function testRenderRejectsACorrelationName(): void
    {
        $this->expectExceptionMessage('The table of INSERT and REPLACE has no correlation name.');

        new InsertInto(false, null, false, new WriteTarget(new QualifiedName(new Name('t')), new Name('x')));
    }
}
