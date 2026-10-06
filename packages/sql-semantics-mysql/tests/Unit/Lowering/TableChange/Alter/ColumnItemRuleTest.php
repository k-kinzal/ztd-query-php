<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\ColumnItemRule;

#[CoversClass(ColumnItemRule::class)]
#[Medium]
final class ColumnItemRuleTest extends TestCase
{
    public function testItemLowersEveryColumnAction(): void
    {
        self::assertSame('ALTER TABLE t ADD COLUMN a INT, CHANGE COLUMN b c INT, MODIFY COLUMN d INT, ALTER COLUMN e SET DEFAULT 1, ALTER COLUMN f DROP DEFAULT', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('alter table t add column a int, change b c int, modify d int, alter e set default 1, alter f drop default')->toString());
    }

    public function testClaimsTheColumnActions(): void
    {
        self::assertSame('ALTER TABLE t ALTER COLUMN a SET DEFAULT 1', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALTER a SET DEFAULT 1')->toString());
    }

    public function testAlteredLowersDefaultsAndVisibility(): void
    {
        self::assertSame('ALTER TABLE t ALTER COLUMN a DROP DEFAULT, ALTER COLUMN b SET VISIBLE', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALTER a DROP DEFAULT, ALTER b SET VISIBLE')->toString());
    }

    public function testColumnAcceptsTheOptionalKeyword(): void
    {
        self::assertSame('ALTER TABLE t DROP COLUMN a', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t DROP COLUMN a')->toString());
    }

    public function testOptionalAcceptsNoKeyword(): void
    {
        self::assertSame('ALTER TABLE t DROP COLUMN a', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t DROP a')->toString());
    }

    public function testDefinitionLowersQualifiedNamesOf57(): void
    {
        self::assertSame('ALTER TABLE t CHANGE COLUMN t.a b INT', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t CHANGE t.a b INT')->toString());
    }

    public function testNameLowersAnUnqualifiedName(): void
    {
        self::assertSame('ALTER TABLE t ALTER COLUMN `a b` SET INVISIBLE', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t ALTER `a b` SET INVISIBLE')->toString());
    }

    public function testPositionLowersFirstAndAfter(): void
    {
        self::assertSame('ALTER TABLE t MODIFY COLUMN a INT FIRST, ADD COLUMN b INT AFTER a', (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t MODIFY a INT FIRST, ADD b INT AFTER a')->toString());
    }

    public function testOrderLowersTheColumns(): void
    {
        self::assertSame('ALTER TABLE t ORDER BY a, b DESC', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t ORDER BY a, b DESC')->toString());
    }
}
