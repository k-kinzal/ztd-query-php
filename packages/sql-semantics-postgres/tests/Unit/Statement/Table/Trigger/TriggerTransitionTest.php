<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerTransition::class)]
#[Medium]
final class TriggerTransitionTest extends TestCase
{
    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER UPDATE ON t REFERENCING OLD TABLE AS o NEW TABLE n FOR EACH STATEMENT EXECUTE FUNCTION f()', []);
        self::assertSame('CREATE TRIGGER g AFTER UPDATE ON t REFERENCING OLD TABLE o NEW TABLE n FOR EACH STATEMENT EXECUTE FUNCTION f()', $statement->toString());
    }
}
