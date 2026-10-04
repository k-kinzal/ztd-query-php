<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\InvalidFactorPair;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RejectedPasswordHash;

#[CoversClass(AlterUser::class)]
#[Medium]
final class AlterUserTest extends TestCase
{
    public function testDeriveStatementReportsFactorAndPasswordHashProblems(): void
    {
        $factors = (new Semantics(Dialect::MySql))->analyze('ALTER USER u MODIFY 2 FACTOR IDENTIFIED BY \'a\' MODIFY 2 FACTOR IDENTIFIED BY \'b\'');
        $hash = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("ALTER USER u IDENTIFIED BY PASSWORD '*AB'");

        self::assertInstanceOf(InvalidFactorPair::class, $factors->facts->diagnostics[0]);
        self::assertInstanceOf(RejectedPasswordHash::class, $hash->facts->diagnostics[0]);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("ALTER USER IF EXISTS u DISCARD OLD PASSWORD, v REQUIRE NONE WITH MAX_QUERIES_PER_HOUR 1 PASSWORD REQUIRE CURRENT OPTIONAL ATTRIBUTE '{}'", (new Semantics(Dialect::MySql))->analyze("alter user if exists u discard old password, v require none with max_queries_per_hour 1 password require current optional attribute '{}'")->toString());
    }
}
