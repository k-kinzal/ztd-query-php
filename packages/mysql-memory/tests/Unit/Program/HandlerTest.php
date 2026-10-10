<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Program\Handler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;

#[CoversClass(Handler::class)]
#[Small]
final class HandlerTest extends TestCase
{
    public function testMarkKeepsWhatIsInScopeWhereTheHandlerRuns(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION SET @a = 1; END');
        $statement = $create->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $statement);
        $block = $statement->body;
        self::assertInstanceOf(Block::class, $block);
        $declaration = $block->declarations[0];
        self::assertInstanceOf(HandlerDeclaration::class, $declaration);

        $handler = new Handler($declaration, $block, [1, 0, 0, 0]);

        self::assertSame([$block, [1, 0, 0, 0]], [$handler->block, $handler->mark]);
    }
}
