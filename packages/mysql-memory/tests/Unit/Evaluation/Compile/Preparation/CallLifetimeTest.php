<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Preparation;

use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Compile\Preparation\CallLifetime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(CallLifetime::class)]
#[Small]
final class CallLifetimeTest extends TestCase
{
    public function testOfDistinguishesNativeLifetimeFromStoredRoutineIdentity(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT `COLLATION`(3), ABS(3), d.ABS(3), CONNECTION_ID()');
        $lifetimes = array_map(static function (int $index) use ($operation): array {
            $call = $operation->field($index)->expression;
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Call\FunctionCall::class, $call);
            return [CallLifetime::of($call), CallLifetime::of($call, true)];
        }, range(0, 3));
        self::assertSame([[Constancy::Row, Constancy::Row], [Constancy::Resolved, Constancy::Resolved], [Constancy::Row, Constancy::Row], [Constancy::Statement, Constancy::Statement]], $lifetimes);
    }
}
