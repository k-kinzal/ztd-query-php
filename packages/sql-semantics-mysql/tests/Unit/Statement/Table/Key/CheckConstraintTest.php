<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;

#[CoversClass(CheckConstraint::class)]
#[Medium]
final class CheckConstraintTest extends TestCase
{
    public function testDeriveElementResolvesTheConditionInTheTable(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, CHECK (a > 0))');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $check = $statement->elements[1];

        self::assertInstanceOf(CheckConstraint::class, $check);
        $resolution = $create->facts->scalar($check->condition)->resolution;
        self::assertNull($resolution);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testDeriveAttributeResolvesTheConditionInTheTable(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT CHECK (b > 0), b INT)');

        self::assertSame([], $create->facts->diagnostics);
    }

    public function testRenderWritesTheNameTheConditionAndTheEnforcement(): void
    {
        self::assertSame('CREATE TABLE t (a INT, CONSTRAINT c CHECK (a > 0) NOT ENFORCED, CHECK (a < 9) ENFORCED)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, CONSTRAINT c CHECK (a > 0) NOT ENFORCED, CHECK (a < 9) ENFORCED)')->toString());
    }
}
