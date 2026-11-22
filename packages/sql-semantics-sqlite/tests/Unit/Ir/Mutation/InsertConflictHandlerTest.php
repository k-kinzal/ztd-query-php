<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflictHandler;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflictHandler
 */
final class InsertConflictHandlerTest extends TestCase
{
    public function testEachValueHasOneStableAction(): void
    {
        self::assertSame([InsertConflictHandler::None, InsertConflictHandler::DoNothing], InsertConflictHandler::cases());
        self::assertSame(InsertConflictHandler::DoNothing, InsertConflictHandler::from('do-nothing'));
    }
}
