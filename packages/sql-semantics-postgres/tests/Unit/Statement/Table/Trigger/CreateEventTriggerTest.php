<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateEventTrigger::class)]
#[Medium]
final class CreateEventTriggerTest extends TestCase
{
    public function testDeriveStatementReportsAnUnknownEventAndVariable(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE EVENT TRIGGER e ON ddl_command_middle WHEN size IN (\'x\') EXECUTE FUNCTION f()', []);
        self::assertSame([
          0 => 'unrecognized event name "ddl_command_middle"',
          1 => 'unrecognized filter variable "size"',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE EVENT TRIGGER e ON ddl_command_end WHEN tag IN (\'CREATE TABLE\') AND TAG IN (\'DROP TABLE\') EXECUTE PROCEDURE s.f()', []);
        self::assertSame('CREATE EVENT TRIGGER e ON ddl_command_end WHEN tag IN (\'CREATE TABLE\') AND tag IN (\'DROP TABLE\') EXECUTE FUNCTION s.f()', $statement->toString());
    }
}
