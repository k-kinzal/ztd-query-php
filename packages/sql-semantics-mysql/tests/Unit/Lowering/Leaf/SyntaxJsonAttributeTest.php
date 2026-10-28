<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Leaf\SyntaxJsonAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\RefusedJsonAttribute;

#[CoversClass(SyntaxJsonAttribute::class)]
#[Small]
final class SyntaxJsonAttributeTest extends TestCase
{
    public function testCompletedReportsAnAbortingOperandCondition(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tree = $semantics->parser()->parse("CREATE TABLE t(a INT) ENGINE_ATTRIBUTE 'bad'");
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering(\SqlSemantics\Contract\Platforms::of('mysql')->productions($semantics->profile()), new \SqlSemantics\Lowering\Leaves(), $semantics->profile());
        $notice = (new SyntaxJsonAttribute())->completed($tree->find('json_attribute')[0], $lowering);
        self::assertTrue($notice?->aborts);
        self::assertInstanceOf(RefusedJsonAttribute::class, $notice->problem);
        self::assertNull((new SyntaxJsonAttribute())->completed($tree, $lowering));
    }

    public function testRejectedRemainsAvailableForAnUnfinishedInput(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tree = $semantics->parser()->parse("CREATE TABLE t(a INT) ENGINE_ATTRIBUTE 'bad'");
        $lowering = new \SqlSemantics\Platform\MySql\Lowering\Lowering(\SqlSemantics\Contract\Platforms::of('mysql')->productions($semantics->profile()), new \SqlSemantics\Lowering\Leaves(), $semantics->profile());
        $failure = (new SyntaxJsonAttribute())->rejected($tree->find('json_attribute')[0], $lowering);
        self::assertInstanceOf(RefusedJsonAttribute::class, $failure?->problem);
        self::assertSame('bad', $failure->problem->attribute->value);
    }

    public function testCompletedDefinitionRetainsAnUnreachableInstructionAndItsCondition(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() BEGIN IF FALSE THEN CREATE TABLE t(a INT ENGINE_ATTRIBUTE 'bad'); END IF; END");
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $statement);
        self::assertInstanceOf(RefusedJsonAttribute::class, $statement->diagnostics()[0]);
        self::assertSame('bad', $statement->diagnostics()[0]->attribute->value);
        self::assertStringContainsString("ENGINE_ATTRIBUTE 'bad'", $statement->toString());
    }
}
