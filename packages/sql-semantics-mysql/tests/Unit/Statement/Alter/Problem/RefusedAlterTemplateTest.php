<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\RefusedAlterTemplate;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(RefusedAlterTemplate::class)]
#[Medium]
final class RefusedAlterTemplateTest extends TestCase
{
    public function testMessageRetainsTheCompleteInstruction(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '5.7.44'))->analyze('CREATE PROCEDURE p() ALTER TABLE t MODIFY g INT AS(USER())');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        $definition = $operation->statement->body;
        self::assertInstanceOf(M\Routine\Program\Write\DeferredAlterTable::class, $definition);
        $problem = new RefusedAlterTemplate($definition);
        self::assertSame($definition, $problem->definition);
        self::assertSame('The alteration retains a generation declaration with an invocation-time condition.', $problem->message());
    }
}
