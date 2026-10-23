<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Server\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\MySql\MySqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Lowering\Server\Transaction\SyntaxLocks;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\LockMode;

#[CoversClass(SyntaxLocks::class)]
#[Medium]
final class SyntaxLocksTest extends TestCase
{
    public function testBeforePublishesOnlyCompletedTopLevelLockOperands(): void
    {
        $parser = new MySqlParser('mysql-5.7.44');
        $profile = new LanguageProfile(GrammarRelease::MySql5744);
        $sql = 'LOCK TABLES t READ, d.u AS a LOW_PRIORITY WRITE,?';
        $locks = (new SyntaxLocks())->before($parser, $sql, strlen($sql), $profile);

        self::assertSame([18, 47], array_column($locks, 0));
        self::assertSame(['t', 'u'], array_map(static fn (array $item): string => $item[1]->table->name->value, $locks));
        self::assertSame(LockMode::LowPriorityWrite, $locks[1][1]->mode);
        self::assertSame('a', $locks[1][1]->alias?->value);
        self::assertCount(1, (new SyntaxLocks())->before($parser, $sql, 18, $profile));
        self::assertSame([], (new SyntaxLocks())->before($parser, 'CREATE PROCEDURE p() LOCK TABLES t READ', 40, $profile));
    }
}
