<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InsertInto::class)]
#[Medium]
final class InsertIntoTest extends TestCase
{
    public function testRenderWritesVerbModifiersAndTable(): void
    {
        self::assertSame('INSERT HIGH_PRIORITY IGNORE INTO t PARTITION (p) (a) VALUES (1)', (new Inspection())->inspect('insert high_priority ignore t partition (p) (a) values (1)')->toString());
        self::assertSame('REPLACE DELAYED INTO t VALUES (1)', (new Inspection('mysql-5.6.51'))->inspect('replace delayed t value (1)')->toString());
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
