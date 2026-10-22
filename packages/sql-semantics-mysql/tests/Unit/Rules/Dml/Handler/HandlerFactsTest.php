<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerFacts;

#[CoversClass(HandlerFacts::class)]
#[Medium]
final class HandlerFactsTest extends TestCase
{
    public function testFunctionsReportsNestedStoredCallsBeforeRelationResolution(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ i = (1) WHERE ABS(d.f())');
        $problems = array_values(array_filter($operation->facts->diagnostics, static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet));

        self::assertCount(1, $problems);
        self::assertSame('stored functions in HANDLER ... READ', $problems[0]->feature);
    }

    public function testFunctionsKeepsNativeCallsAsOrdinaryHandlerExpressions(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ i = (ABS(1)) WHERE ABS(2)');

        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testReadResolvesDefaultAgainstTheOpenHandlerState(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ i = (DEFAULT)');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek::class, $operation->statement);
        $fact = $operation->facts->scalar($operation->statement->values[0]);

        self::assertSame([], $operation->facts->diagnostics);
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Dependent::class, $fact->type);
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Missing\SessionState::class, $fact->type->missing[0]);
    }

    public function testReadRecordsTheRowsOfTheHandler(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ i = (1) WHERE h.a = 2 LIMIT 1');

        self::assertNotNull($operation->facts->output);
        self::assertSame([], $operation->facts->diagnostics);
    }
}
