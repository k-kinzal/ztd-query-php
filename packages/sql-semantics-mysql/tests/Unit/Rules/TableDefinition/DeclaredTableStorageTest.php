<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\DeclaredTableStorage;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(DeclaredTableStorage::class)]
#[Medium]
final class DeclaredTableStorageTest extends TestCase
{
    public function testResolveKeepsTheExplicitEngine(): void
    {
        $result = (new Inspection())->inspect('CREATE TABLE app.t (id INT) ENGINE=MyISAM');
        self::assertInstanceOf(CreateTable::class, $result->statement);

        self::assertSame('MyISAM', (new DeclaredTableStorage())->resolve($result->statement));
    }
}
