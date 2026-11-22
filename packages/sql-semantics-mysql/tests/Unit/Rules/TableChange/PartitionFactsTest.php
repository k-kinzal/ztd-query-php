<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableChange;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Rules\TableChange\PartitionFacts;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(PartitionFacts::class)]
#[Medium]
final class PartitionFactsTest extends TestCase
{
    public function testPartitioningDerivesTheClauseInTheScope(): void
    {
        $derivation = new Derivation((new Inspection())->context([]));
        $clause = new PartitionClause(new HashMethod(false, new ColumnUse(new Name('a'))), null, null, []);

        (new PartitionFacts())->partitioning($clause, $derivation, $derivation->environment());

        self::assertSame('Column a does not exist.', $derivation->facts()->diagnostics[0]->message());
    }
}
