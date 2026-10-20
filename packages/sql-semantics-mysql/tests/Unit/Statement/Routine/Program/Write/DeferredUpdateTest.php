<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredUpdate;

#[CoversClass(DeferredUpdate::class)]
#[Small]
final class DeferredUpdateTest extends TestCase
{
    public function testValueRejectsAnOffsetInTheRowLimit(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() UPDATE t,u SET t.a=1 LIMIT 1')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $statement);
        $statement = $statement->body;
        self::assertInstanceOf(DeferredUpdate::class, $statement);
        $limit = new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), \SqlSemantics\Platform\MySql\Statement\Query\Clause\OffsetSpelling::Comma);
        $this->expectExceptionMessage('UPDATE limits rows without an offset.');
        new DeferredUpdate($statement->with, $statement->lowPriority, $statement->ignore, $statement->tables, $statement->assignments, limit: $limit);
    }

    public function testDeriveProgramRetainsTheCompleteWrite(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=1");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredUpdate::class, $operation->statement->body);
        self::assertStringContainsString('UPDATE JSON_TABLE', $operation->toString());
    }
    public function testRenderPreservesTheStoredUpdate(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() UPDATE LOW_PRIORITY IGNORE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,b FOR ORDINALITY)) j SET b=1 WHERE 0 ORDER BY b LIMIT 2");
        self::assertStringContainsString('UPDATE LOW_PRIORITY IGNORE', $operation->toString());
        self::assertStringContainsString('WHERE 0 ORDER BY b LIMIT 2', $operation->toString());
    }
}
