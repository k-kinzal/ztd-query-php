<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\TableChange\StatementRule;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StatementRule::class)]
#[Medium]
final class StatementRuleTest extends TestCase
{
    public function testStatementLowersEveryStatementOfTheFamily(): void
    {
        self::assertSame('DROP INDEX i ON t', (new Inspection('mysql-5.6.51'))->inspect('drop index i on t')->toString());
    }

    public function testDropTableLowersEveryPart(): void
    {
        self::assertSame('DROP TEMPORARY TABLE IF EXISTS a, b.c CASCADE', (new Inspection('mysql-5.7.44'))->inspect('drop temporary table if exists a, b.c cascade')->toString());
    }

    public function testRenameLowersTheRenames(): void
    {
        self::assertSame('RENAME TABLE a TO b, c TO d', (new Inspection())->inspect('rename table a to b, c to d')->toString());
    }

    public function testRenameUsersHandsTheListToTheAccountFamily(): void
    {
        $operation = (new Inspection())->inspect('rename user a to b, c to d');

        self::assertInstanceOf(RenameUser::class, $operation->statement);
        self::assertSame('RENAME USER a TO b, c TO d', $operation->toString());
    }

    public function testTruncateLowersTheTable(): void
    {
        self::assertSame('TRUNCATE TABLE t', (new Inspection('mysql-5.6.51'))->inspect('truncate table t')->toString());
    }
}
