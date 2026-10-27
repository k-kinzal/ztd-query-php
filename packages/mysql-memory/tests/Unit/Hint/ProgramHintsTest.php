<?php

declare(strict_types=1);

namespace Tests\Unit\Hint;

use MySqlMemory\Hint\ProgramHints;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;

#[CoversClass(ProgramHints::class)]
#[Small]
final class ProgramHintsTest extends TestCase
{
    public function testWarningsKeepIndependentBlockNamesAndOneTrailingResourceCondition(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->semantics()->analyze('CREATE PROCEDURE p() BEGIN SELECT /*+ NO_ICP(t@x i) MAX_EXECUTION_TIME(2) RESOURCE_GROUP(a) */ 1; SELECT /*+ NO_ICP(t@y i) RESOURCE_GROUP(b) */ 2; END');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $session->text = 'unrelated invalid SQL';
        self::assertSame([3127, 3125, 3127, 3515], array_column((new ProgramHints())->warnings($statement, $session), 0));
        self::assertSame('unrelated invalid SQL', $session->text);
    }

    public function testBlocksPreserveSeparateNamespacesForEachInstruction(): void
    {
        $session = (new Instance())->connect();
        $statement = $session->semantics()->analyze('CREATE PROCEDURE p() BEGIN SELECT /*+ QB_NAME(x) */ 1; SELECT /*+ NO_ICP(t@x i) */ 2; END');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $statement);
        self::assertInstanceOf(CreateProcedure::class, $statement);
        $blocks = (new ProgramHints())->blocks($statement->body, $session);
        self::assertCount(2, $blocks);
        self::assertSame([1], array_keys($blocks[0]->blocks));
        self::assertSame([1], array_keys($blocks[1]->blocks));
        self::assertSame([3127], array_column((new ProgramHints())->warnings($statement, $session), 0));
    }
}
