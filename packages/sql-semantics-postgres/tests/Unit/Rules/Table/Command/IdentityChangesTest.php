<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\Command\IdentityChanges::class)]
#[Medium]
final class IdentityChangesTest extends TestCase
{
    public function testCheckedRefusesAnotherNode(): void
    {
        $this->expectExceptionMessage('An identity change is RESTART, SET with a sequence option, or SET GENERATED.');
        (new \SqlSemantics\Platform\PostgreSql\Rules\Table\Command\IdentityChanges())->checked([new \SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DefaultBound()]);
    }
}
