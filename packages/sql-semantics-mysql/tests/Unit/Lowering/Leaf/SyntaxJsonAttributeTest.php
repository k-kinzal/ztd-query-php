<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Leaf;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\RejectedInput;
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

    public function testRejectedPreventsPublishingAnInvalidProgramInstruction(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze("CREATE PROCEDURE p() BEGIN IF FALSE THEN CREATE TABLE t(a INT ENGINE_ATTRIBUTE 'bad'); END IF; END");
            self::fail('An unreachable instruction must still have valid JSON operands.');
        } catch (RejectedInput $failure) {
            self::assertInstanceOf(RefusedJsonAttribute::class, $failure->problem);
            self::assertSame('bad', $failure->problem->attribute->value);
            self::assertSame(0, $failure->problem->position);
            self::assertTrue($failure->aborts);
        }
    }
}
