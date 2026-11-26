<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Alter\ColumnItemRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ColumnItemRule::class)]
#[Medium]
final class ColumnItemRuleTest extends TestCase
{
    public function testItemLowersEveryColumnAction(): void
    {
        self::assertSame('ALTER TABLE t ADD COLUMN a INT, CHANGE COLUMN b c INT, MODIFY COLUMN d INT, ALTER COLUMN e SET DEFAULT 1, ALTER COLUMN f DROP DEFAULT', (new Inspection('mysql-5.6.51'))->inspect('alter table t add column a int, change b c int, modify d int, alter e set default 1, alter f drop default')->toString());
    }

    public function testClaimsTheColumnActions(): void
    {
        self::assertSame('ALTER TABLE t ALTER COLUMN a SET DEFAULT 1', (new Inspection())->inspect('ALTER TABLE t ALTER a SET DEFAULT 1')->toString());
    }

    public function testAlteredLowersDefaultsAndVisibility(): void
    {
        self::assertSame('ALTER TABLE t ALTER COLUMN a DROP DEFAULT, ALTER COLUMN b SET VISIBLE', (new Inspection())->inspect('ALTER TABLE t ALTER a DROP DEFAULT, ALTER b SET VISIBLE')->toString());
    }

    public function testColumnAcceptsTheOptionalKeyword(): void
    {
        self::assertSame('ALTER TABLE t DROP COLUMN a', (new Inspection())->inspect('ALTER TABLE t DROP COLUMN a')->toString());
    }

    public function testOptionalAcceptsNoKeyword(): void
    {
        self::assertSame('ALTER TABLE t DROP COLUMN a', (new Inspection())->inspect('ALTER TABLE t DROP a')->toString());
    }

    public function testDefinitionLowersQualifiedNamesOf57(): void
    {
        self::assertSame('ALTER TABLE t CHANGE COLUMN t.a b INT', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t CHANGE t.a b INT')->toString());
    }

    public function testNameLowersAnUnqualifiedName(): void
    {
        self::assertSame('ALTER TABLE t ALTER COLUMN `a b` SET INVISIBLE', (new Inspection())->inspect('ALTER TABLE t ALTER `a b` SET INVISIBLE')->toString());
    }

    public function testPositionLowersFirstAndAfter(): void
    {
        self::assertSame('ALTER TABLE t MODIFY COLUMN a INT FIRST, ADD COLUMN b INT AFTER a', (new Inspection())->inspect('ALTER TABLE t MODIFY a INT FIRST, ADD b INT AFTER a')->toString());
    }

    public function testOrderLowersTheColumns(): void
    {
        self::assertSame('ALTER TABLE t ORDER BY a, b DESC', (new Inspection('mysql-5.7.44'))->inspect('ALTER TABLE t ORDER BY a, b DESC')->toString());
    }
}
