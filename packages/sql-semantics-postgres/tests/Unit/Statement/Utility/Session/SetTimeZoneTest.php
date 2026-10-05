<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetTimeZone::class)]
#[Medium]
final class SetTimeZoneTest extends TestCase
{
    public function testDeriveStatementTypesTheIntervalConstant(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SET TIME ZONE INTERVAL '1' HOUR");
        $statement = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetTimeZone::class, $statement);
        $zone = $statement->zone;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Literal\TypedLiteral::class, $zone);
        self::assertSame(\SqlSemantics\Statement\Type\Nullability::NotNull, $operation->facts->scalar($zone)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveStatementReportsOtherIntervalFields(): void
    {
        self::assertSame('time zone interval must be HOUR or HOUR TO MINUTE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TIME ZONE INTERVAL \'1\' DAY TO HOUR')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementAcceptsAPrecision(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TIME ZONE INTERVAL (2) \'1\'')->facts->diagnostics);
    }

    public function testRenderQuotesAnIdentifierThatIsAKeyword(): void
    {
        self::assertSame('SET LOCAL TIME ZONE "local"', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET LOCAL TIME ZONE "local"')->toString());
    }

    public function testRenderWritesEachValueForm(): void
    {
        self::assertSame(["SET TIME ZONE 'UTC'", 'SET TIME ZONE utc', 'SET TIME ZONE - 8', "SET TIME ZONE INTERVAL '-08:00' HOUR TO MINUTE"], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SET TIME ZONE 'UTC'")->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TIME ZONE UTC')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TIME ZONE -8')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SET TIME ZONE INTERVAL '-08:00' HOUR TO MINUTE")->toString()]);
    }
}
