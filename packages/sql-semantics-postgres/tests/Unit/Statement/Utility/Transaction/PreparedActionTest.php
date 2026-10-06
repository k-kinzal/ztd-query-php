<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\PreparedAction::class)]
#[Medium]
final class PreparedActionTest extends TestCase
{
    public function testEachStepIsRead(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ROLLBACK PREPARED \'x\'')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\PreparedTransaction::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\PreparedAction::Rollback, $statement->action);
    }
}
