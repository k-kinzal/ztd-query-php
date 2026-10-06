<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\PolicyCommand::class)]
#[Medium]
final class PolicyCommandTest extends TestCase
{
    public function testCasesSpellTheCommands(): void
    {
        self::assertSame([
          0 => 'ALL',
          1 => 'SELECT',
          2 => 'INSERT',
          3 => 'UPDATE',
          4 => 'DELETE',
        ], array_map(static fn ($command): string => $command->value, \SqlSemantics\Platform\PostgreSql\Statement\Table\Policy\PolicyCommand::cases()));
    }
}
