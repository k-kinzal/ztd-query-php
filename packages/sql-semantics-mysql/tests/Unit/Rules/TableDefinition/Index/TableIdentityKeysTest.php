<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\CreateIndex;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\TableIdentityKeys;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(TableIdentityKeys::class)]
#[Medium]
final class TableIdentityKeysTest extends TestCase
{
    public function testHasIdentityUsesThePriorVisibleRequiredUniqueKey(): void
    {
        $semantics = new Semantics();
        $context = $semantics->context([
            'CREATE TABLE app.events (a INT NOT NULL, b INT NOT NULL, UNIQUE INDEX prior_identity (b)) ENGINE=InnoDB',
        ]);
        $index = $semantics->analyze('CREATE UNIQUE INDEX hidden ON app.events (a) INVISIBLE', $context);

        self::assertInstanceOf(CreateIndex::class, $index);
        self::assertTrue($index->ownerHasIdentityKey);
        self::assertFalse($index->visible);
    }
}
