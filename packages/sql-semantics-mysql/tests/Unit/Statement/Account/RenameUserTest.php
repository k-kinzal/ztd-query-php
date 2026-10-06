<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;

#[CoversClass(RenameUser::class)]
#[Medium]
final class RenameUserTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('RENAME USER a TO b')->facts->diagnostics);
    }

    public function testRenderWritesThePairs(): void
    {
        self::assertSame('RENAME USER a@h TO b, c TO d', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("rename user 'a'@'h' to b, c to d")->toString());
    }
}
