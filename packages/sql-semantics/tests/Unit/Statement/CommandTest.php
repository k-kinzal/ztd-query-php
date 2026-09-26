<?php

declare(strict_types=1);

namespace Tests\Unit\Statement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Command;
use SqlSemantics\Statement\Model\Sqlite\Value\CmdWithCommitEndTransOpt_ccca6149 as Commit;
use SqlSemantics\Statement\Model\Sqlite\Value\TransOptWithTransaction_ea573324 as Transaction;
use SqlSemantics\Statement\Statement;

#[CoversClass(Command::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Statement\Writer::class)]
#[UsesClass(Statement::class)]
#[Medium]
final class CommandTest extends TestCase
{
    public function testWriteWithCompleteCommand(): void
    {
        $command = new Commit('COMMIT', new Transaction());
        $statement = new Statement($command);
        self::assertSame('COMMIT TRANSACTION', $statement->toString());
    }
}
