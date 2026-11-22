<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(RenameUser::class)]
#[Medium]
final class RenameUserTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Inspection())->inspect('RENAME USER a TO b')->facts->diagnostics);
    }

    public function testRenderWritesThePairs(): void
    {
        self::assertSame('RENAME USER a@h TO b, c TO d', (new Inspection('mysql-5.7.44'))->inspect("rename user 'a'@'h' to b, c to d")->toString());
    }
}
