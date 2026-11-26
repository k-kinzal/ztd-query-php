<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ConstraintName;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ConstraintName::class)]
#[Medium]
final class ConstraintNameTest extends TestCase
{
    public function testRenderWritesConstraintAndTheSymbol(): void
    {
        self::assertSame('CREATE TABLE t (a INT, CONSTRAINT PRIMARY KEY (a), CONSTRAINT c UNIQUE (a))', (new Inspection())->inspect('CREATE TABLE t (a INT, CONSTRAINT PRIMARY KEY (a), CONSTRAINT c UNIQUE (a))')->toString());
    }
}
