<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\EventFilter::class)]
#[Medium]
final class EventFilterTest extends TestCase
{
    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE EVENT TRIGGER e ON ddl_command_start WHEN tag IN (\'A\', \'B\') EXECUTE FUNCTION f()', []);
        self::assertSame('CREATE EVENT TRIGGER e ON ddl_command_start WHEN tag IN (\'A\', \'B\') EXECUTE FUNCTION f()', $statement->toString());
    }
}
