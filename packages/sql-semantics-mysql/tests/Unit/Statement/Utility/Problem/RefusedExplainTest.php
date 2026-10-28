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
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() INSERT INTO t SET a=ROW(1,2)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet::class, $operation->body);
        $explanation = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain($operation->body);
        self::assertSame('Operand should contain 1 column(s), not 2.', (new RefusedExplain($explanation))->message());
    }

    public function testMessageRetainsTheCompletePreparationDependencies(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE PROCEDURE p() EXPLAIN FORMAT=JSON INTO @'' SELECT 1");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain::class, $operation->body);
        $problem = new RefusedExplain($operation->body);
        self::assertSame("User variable name '' is illegal", $problem->message());
        self::assertSame($operation->body, $problem->explanation);
    }

    public function testMessageKeepsTheExplainedUpdateColumnConstraint(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE PROCEDURE p() EXPLAIN UPDATE JSON_TABLE('[]', '$[*]' COLUMNS (b FOR ORDINALITY,B FOR ORDINALITY)) j SET b=2");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Statement::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain::class, $operation->body);
        $explanation = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain($operation->body->statement, new \SqlSemantics\Statement\Identifier\Name('JSON'), new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('')));
        $problem = new RefusedExplain($explanation);
        self::assertSame('Duplicate column name b.', $problem->message());
        self::assertSame($explanation, $problem->explanation);
    }
}
