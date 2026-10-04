<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableChange\PartitionFacts;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(PartitionFacts::class)]
#[Medium]
final class PartitionFactsTest extends TestCase
{
    public function testPartitioningDerivesTheClauseInTheScope(): void
    {
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $clause = new PartitionClause(new HashMethod(false, new ColumnUse(new Name('a'))), null, null, []);

        (new PartitionFacts())->partitioning($clause, $derivation, $derivation->environment());

        self::assertSame('Column a does not exist.', $derivation->facts()->diagnostics[0]->message());
    }
}
