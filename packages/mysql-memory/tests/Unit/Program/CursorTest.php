<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Program\Cursor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Cursor\CursorDeclaration;

#[CoversClass(Cursor::class)]
#[Small]
final class CursorTest extends TestCase
{
    public function testRowsAreNullUntilTheCursorIsOpened(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; END');
        $statement = $create->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $statement);
        $body = $statement->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Block::class, $body);
        $declaration = $body->declarations[0];
        self::assertInstanceOf(CursorDeclaration::class, $declaration);

        $cursor = new Cursor($declaration);

        self::assertSame([null, [], 0], [$cursor->rows, $cursor->domains, $cursor->position]);
    }
}
