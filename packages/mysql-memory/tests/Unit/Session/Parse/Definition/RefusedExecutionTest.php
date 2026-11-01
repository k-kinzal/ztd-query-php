<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Parse\Definition;

use MySqlMemory\Session\Parse\Definition\RefusedExecution;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedExecution::class)]
#[Small]
final class RefusedExecutionTest extends TestCase
{
    public function testCarriesTheRefusedDefinitionAndItsMessage(): void
    {
        $refused = new RefusedCreateTable(new DeferredCreateTable(new QualifiedName(new Name('t'))), new NoColumns());
        $transfer = new RefusedExecution($refused);

        self::assertSame($refused, $transfer->definition);
        self::assertSame('A table must have at least 1 column.', $transfer->getMessage());
    }
}
