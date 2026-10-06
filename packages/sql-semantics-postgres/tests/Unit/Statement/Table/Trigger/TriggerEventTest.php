<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEvent::class)]
#[Medium]
final class TriggerEventTest extends TestCase
{
    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER UPDATE OF a, b OR INSERT ON t EXECUTE FUNCTION f()', []);
        self::assertSame('CREATE TRIGGER g AFTER UPDATE OF a, b OR INSERT ON t EXECUTE FUNCTION f()', $statement->toString());
    }

    public function testRefusesColumnsForInsert(): void
    {
        $this->expectExceptionMessage('Only UPDATE names columns.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEvent(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerEventKind::Insert, [new \SqlSemantics\Statement\Identifier\Name('a')]);
    }
}
