<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Constraint\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction::class)]
#[Medium]
final class ReferentialActionTest extends TestCase
{
    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE t (a int, b int, FOREIGN KEY (a, b) REFERENCES u ON DELETE SET DEFAULT (b) ON UPDATE NO ACTION)', []);
        self::assertSame('CREATE TABLE t (a INT, b INT, FOREIGN KEY (a, b) REFERENCES u ON DELETE SET DEFAULT (b) ON UPDATE NO ACTION)', $statement->toString());
    }

    public function testRefusesColumnsForAnActionThatSetsNothing(): void
    {
        $this->expectExceptionMessage('Only SET NULL and SET DEFAULT name columns.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferentialAction(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceEvent::Delete, \SqlSemantics\Platform\PostgreSql\Statement\Constraint\Reference\ReferenceAction::Cascade, [new \SqlSemantics\Statement\Identifier\Name('a')]);
    }
}
