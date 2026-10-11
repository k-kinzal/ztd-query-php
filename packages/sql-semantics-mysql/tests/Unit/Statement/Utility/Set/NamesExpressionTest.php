<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\NamesExpression;

#[CoversClass(NamesExpression::class)]
#[Medium]
final class NamesExpressionTest extends TestCase
{
    public function testDeriveItemReportsTheRefusal(): void
    {
        $set = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('SET NAMES = 1');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $set->statement);
        self::assertInstanceOf(NamesExpression::class, $set->statement->items[0]);
        self::assertSame('SET NAMES takes a character set name, not an expression', $set->facts->diagnostics[0]->message());
    }

    public function testRenderWritesAnEqualsSign(): void
    {
        self::assertSame('SET @a = 1, NAMES = @b', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set @a = 1, names := @b')->toString());
    }

    public function testDeriveItemRetainsTheFailurePositionAfterExpressionWarnings(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SET NAMES = !1, @a = !2');
        $failure = $operation->facts->warnings[2];

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables::class, $operation->statement);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $failure);
        self::assertTrue($failure->aborts);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse::class, $failure->problem);
        self::assertSame($operation->statement->items[0], $failure->problem->subject);
        self::assertNotNull($operation->sources->of($failure->problem->subject));
    }

    public function testProblemDistinguishesALocalVariableFromTheConnectionCharset(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() BEGIN DECLARE names INT; SET NAMES = 1; END');

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityMisuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::NamesShadow, $operation->facts->diagnostics[0]->rule);
    }
}
