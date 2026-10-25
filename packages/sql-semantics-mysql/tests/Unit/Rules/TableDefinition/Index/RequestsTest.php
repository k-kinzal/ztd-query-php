<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(\SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Requests::class)]
#[Medium]
final class RequestsTest extends TestCase
{
    public function testDeriveBindsTheSameTargetForOrdinaryAndRetainedInstructions(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT)');
        $ordinary = $semantics->analyze('CREATE INDEX i ON t(a)', [$table]);
        $retained = $semantics->analyze('CREATE PROCEDURE p() CREATE INDEX i ON t ((ROW(a, 2)))', [$table]);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $retained->statement);
        $instruction = $retained->statement->body;
        self::assertInstanceOf(M\Routine\Program\Write\Index\DeferredCreateIndex::class, $instruction);
        self::assertEquals($ordinary->facts->relation($ordinary->statement)->table, $retained->facts->relation($instruction)->table);
        self::assertSame('CREATE INDEX i ON t (a)', $ordinary->toString());
        self::assertSame('CREATE PROCEDURE p() CREATE INDEX i ON t ((ROW(a, 2)))', $retained->toString());
    }
    public function testRenderRetainsBothIndexAndAlterationOptions(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() CREATE INDEX i USING BTREE ON d.t ((ROW(1,2)) DESC) COMMENT 'retained' ALGORITHM=INPLACE LOCK=NONE");
        self::assertSame("CREATE PROCEDURE p() CREATE INDEX i USING BTREE ON d.t ((ROW(1, 2)) DESC) COMMENT 'retained' ALGORITHM = INPLACE LOCK = NONE", $operation->toString());
    }

}
