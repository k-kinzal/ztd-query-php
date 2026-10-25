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
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ABS(d.f())');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $operation->statement);
        $derivation = new \SqlSemantics\Construction\Derivation($operation->context);
        (new HandlerFacts())->functions([$operation->statement->query], $derivation);
        $problems = array_values(array_filter($derivation->facts()->diagnostics, static fn ($problem): bool => $problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\NotSupportedYet));

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

    public function testReadResolvesKeyColumnsAgainstTheSameOpenHandlerAsItsPredicate(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE d.t(a INT)')->declarations()[0];
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_bin'), handlers: ['h' => $table->name]);
        $operation = $semantics->analyze('HANDLER h READ k = (a) WHERE a', $semantics->context([$table], session: $settings));
        $statement = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek::class, $statement);
        $key = $operation->facts->scalar($statement->values[0])->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $key);
        self::assertSame($statement->handler, $key->relation);
        self::assertNotNull($statement->where);
        $predicate = $operation->facts->scalar($statement->where)->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $predicate);
        self::assertSame($key->relation, $predicate->relation);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testReadRecordsTheRowsOfTheHandler(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('HANDLER h READ i = (1) WHERE h.a = 2 LIMIT 1');

        self::assertNotNull($operation->facts->output);
        self::assertSame([], $operation->facts->diagnostics);
    }
}
