<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\ItemRule;

#[CoversClass(ItemRule::class)]
#[Medium]
final class ItemRuleTest extends TestCase
{
    public function testItemLowersKeysAndConstraints(): void
    {
        self::assertSame('ALTER TABLE t ADD INDEX i (a), DISABLE KEYS, FORCE', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ADD INDEX i (a), DISABLE KEYS, FORCE')->toString());
    }

    public function testSimpleLowersTheModifiersOf56(): void
    {
        self::assertSame('ALTER TABLE t ALGORITHM = COPY, LOCK = SHARED', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('ALTER TABLE t ALGORITHM = COPY, LOCK = SHARED')->toString());
    }

    public function testDropLowersEveryKind(): void
    {
        self::assertSame('ALTER TABLE t DROP FOREIGN KEY f, DROP PRIMARY KEY, DROP INDEX i', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t DROP FOREIGN KEY f, DROP PRIMARY KEY, DROP INDEX i')->toString());
    }

    public function testNamedLowersRenamesAndAlters(): void
    {
        self::assertSame('ALTER TABLE t RENAME COLUMN a TO b, ALTER CHECK c NOT ENFORCED, ALTER INDEX i INVISIBLE', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t RENAME COLUMN a TO b, ALTER CHECK c NOT ENFORCED, ALTER INDEX i INVISIBLE')->toString());
    }

    public function testRenameLowersEveryWord(): void
    {
        self::assertSame('ALTER TABLE t RENAME TO u', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t RENAME AS u')->toString());
    }

    public function testConvertLowersDefault(): void
    {
        self::assertSame('ALTER TABLE t CONVERT TO CHARACTER SET DEFAULT', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t CONVERT TO CHARACTER SET DEFAULT')->toString());
    }
}
