<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Transaction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointAction::class)]
#[Medium]
final class SavepointActionTest extends TestCase
{
    public function testEachCommandHasItsAction(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('RELEASE s')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointCommand::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Transaction\SavepointAction::Release, $statement->action);
    }
}
