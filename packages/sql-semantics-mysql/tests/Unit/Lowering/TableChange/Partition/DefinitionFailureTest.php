<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\TableChange\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\TableChange\Partition\DefinitionFailure;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\DefinitionIssue;

#[CoversClass(DefinitionFailure::class)]
#[Small]
final class DefinitionFailureTest extends TestCase
{
    public function testFailureKeepsTheFirstConflictingSubpartitionBoundary(): void
    {
        $sql = 'ALTER TABLE d.t ADD PARTITION (PARTITION p0, PARTITION p1 (SUBPARTITION s1))';
        try {
            (new Semantics(Dialect::MySql))->analyze($sql);
            self::fail('Contradictory subpartition definitions must be refused.');
        } catch (RejectedInput $failure) {
            self::assertSame(DefinitionIssue::SubpartitionCount, $failure->problem);
            self::assertSame(strpos($sql, 'SUBPARTITION s1'), $failure->end);
        }
    }
}
