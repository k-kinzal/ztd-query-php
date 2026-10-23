<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedExplain;

#[CoversClass(RefusedExplain::class)]
#[Medium]
final class RefusedExplainTest extends TestCase
{
    public function testMessageRetainsTheExplainedSetWriteCondition(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() EXPLAIN INSERT INTO t SET a=ROW(1,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain::class, $operation->statement->body);
        self::assertSame('Operand should contain 1 column(s), not 2.', (new RefusedExplain($operation->statement->body))->message());
    }

    public function testMessageRetainsTheCompletePreparationDependencies(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE PROCEDURE p() EXPLAIN FORMAT=JSON INTO @'' SELECT 1");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain::class, $operation->statement->body);
        $problem = new RefusedExplain($operation->statement->body);
        self::assertSame("User variable name '' is illegal", $problem->message());
        self::assertSame($operation->statement->body, $problem->explanation);
    }

    public function testMessageKeepsTheExplainedUpdateColumnConstraint(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() EXPLAIN UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,B FOR ORDINALITY)) j SET b=2");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain::class, $operation->statement->body);
        $problem = new RefusedExplain($operation->statement->body);
        self::assertSame('Duplicate column name b.', $problem->message());
        self::assertSame($operation->statement->body, $problem->explanation);
    }
}
