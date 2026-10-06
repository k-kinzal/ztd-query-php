<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\RoutineFacts;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\MissingColumn;

#[CoversClass(RoutineFacts::class)]
#[Medium]
final class RoutineFactsTest extends TestCase
{
    public function testCreateHidesParametersFromDefaults(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE FUNCTION f(a int4, b int4 DEFAULT a) RETURNS int4 RETURN b', []);
        self::assertEquals([new MissingColumn(new Name('a'))], $operation->facts->diagnostics);
    }
}
