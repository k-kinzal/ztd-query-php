<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\TriggerArgument::class)]
#[Medium]
final class TriggerArgumentTest extends TestCase
{
    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TRIGGER g AFTER INSERT ON t EXECUTE FUNCTION f(42, 1.5e3, \'text\', "Word")', []);
        self::assertSame('CREATE TRIGGER g AFTER INSERT ON t EXECUTE FUNCTION f(42, 1.5e3, \'text\', "Word")', $statement->toString());
    }
}
