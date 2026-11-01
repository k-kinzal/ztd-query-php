<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Problem\Creation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Definition\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedCreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\NoColumns;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RefusedCreateTable::class)]
#[Small]
final class RefusedCreateTableTest extends TestCase
{
    public function testMessageIsTheProblemOfTheDefinition(): void
    {
        $definition = new DeferredCreateTable(new QualifiedName(new Name('t')));
        $refused = new RefusedCreateTable($definition, new NoColumns());

        self::assertSame($definition, $refused->definition);
        self::assertSame('A table must have at least 1 column.', $refused->message());
    }
}
