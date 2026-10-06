<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;

#[CoversClass(Assignment::class)]
#[Medium]
final class AssignmentTest extends TestCase
{
    public function testRenderWritesColumnAndValue(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('UPDATE t SET t.a := 1');
        self::assertInstanceOf(Update::class, $operation->statement);

        self::assertSame('a', $operation->statement->assignments[0]->column->name->value);
        self::assertSame('UPDATE t SET t.a = 1', $operation->toString());
    }
}
