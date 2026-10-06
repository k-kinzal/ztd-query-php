<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand::class)]
#[Medium]
final class AlterCommandTest extends TestCase
{
    public function testActionsAreAlterCommands(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t SET LOGGED, NOT OF', []);
        self::assertSame('ALTER TABLE t SET LOGGED, NOT OF', $statement->toString());
    }
}
