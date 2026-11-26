<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateColumnLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(CreateColumnLowering::class)]
#[Small]
final class CreateColumnLoweringTest extends TestCase
{
    public function testLowerRetainsBothTheDefaultAndNullability(): void
    {
        $operation = (new Inspection())->inspect('CREATE TABLE c (a INTEGER DEFAULT -7 NOT NULL)');
        self::assertInstanceOf(CreateTable::class, $operation->statement);

        $binding = $operation->binding();
        $source = new SourceBinding($binding, $operation->statement, [new Identifier('a')]);
        $column = (new CreateColumnLowering())->lower($operation->statement->columns[0], $binding, $source, $binding->facts->declarations[0]->columns[0]->type);

        self::assertSame(NotNullConflict::Abort, $column->notNull);
        self::assertInstanceOf(IntegerLiteral::class, $column->default);
        self::assertSame('-7', $column->default->decimal);
    }
}
