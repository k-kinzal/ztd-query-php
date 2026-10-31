<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Expression\MergeDependencies;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;

#[CoversClass(MergeDependencies::class)]
#[Medium]
final class MergeDependenciesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, GrammarRelease, bool}>
     */
    public static function providerExpressions(): iterable
    {
        yield 'column' => ['id', GrammarRelease::MySql847, false];
        yield 'reduced column' => ['(SELECT id)', GrammarRelease::MySql847, false];
        yield 'nested reduced column' => ['(SELECT (SELECT id))', GrammarRelease::MySql847, false];
        yield 'legacy retained column' => ['(SELECT id)', GrammarRelease::MySql5744, true];
        yield 'legacy nested column' => ['(SELECT (SELECT id))', GrammarRelease::MySql5744, true];
        yield 'independent input' => ['(SELECT q.id FROM t q LIMIT 1)', GrammarRelease::MySql847, false];
        yield 'dependent input' => ['(SELECT q.id FROM t q WHERE q.id=t.id)', GrammarRelease::MySql847, true];
        yield 'dependent membership' => ['id IN (SELECT id FROM t)', GrammarRelease::MySql847, true];
        yield 'legacy constant membership' => ['1 IN (SELECT id FROM t)', GrammarRelease::MySql5744, true];
        yield 'constant membership' => ['1 IN (SELECT id FROM t)', GrammarRelease::MySql847, false];
        yield 'reduced membership' => ['id IN (SELECT 1)', GrammarRelease::MySql847, false];
        yield 'retained random call' => ['(SELECT RAND(1) FROM t LIMIT 1)', GrammarRelease::MySql847, true];
        yield 'retained variable read' => ['(SELECT @v FROM t LIMIT 1)', GrammarRelease::MySql847, true];
        yield 'retained assignment' => ['(SELECT @v:=1 FROM t LIMIT 1)', GrammarRelease::MySql847, true];
        yield 'reduced random call' => ['(SELECT RAND(1))', GrammarRelease::MySql847, false];
        yield 'variable assignment' => ['@v:=id', GrammarRelease::MySql847, true];
        yield 'reduced assignment' => ['(SELECT @v:=id)', GrammarRelease::MySql847, true];
    }

    #[DataProvider('providerExpressions')]
    public function testSeparateRetainsTheDependenciesOfUnreducedSubqueries(string $expression, GrammarRelease $release, bool $separate): void
    {
        $semantics = new Semantics(Dialect::MySql, $release->value);
        $table = $semantics->analyze('CREATE TABLE t(id INT PRIMARY KEY)');
        $operation = $semantics->analyze('SELECT ' . $expression . ' FROM t', [$table]);
        $selected = $operation->field(0)->expression;
        self::assertNotNull($selected);

        self::assertSame($separate, (new MergeDependencies())->separate([$selected], $release));
    }

    public function testChangingFindsEffectsAcrossNestedQueries(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $pure = $semantics->analyze('SELECT 1 FROM t');
        $changing = $semantics->analyze('SELECT (SELECT RAND(1)) FROM t');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $pure);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $changing);

        self::assertFalse((new MergeDependencies())->changing($pure->query, GrammarRelease::MySql847));
        self::assertTrue((new MergeDependencies())->changing($changing->query, GrammarRelease::MySql847));
    }

    public function testExternalCountsQueryBlocksThroughNestedWrappers(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(id INT PRIMARY KEY)');
        $local = $semantics->analyze('SELECT (SELECT (SELECT q.id) FROM t q ORDER BY q.id LIMIT 1) FROM t', [$table]);
        $outer = $semantics->analyze('SELECT (SELECT (SELECT t.id) FROM t q ORDER BY q.id LIMIT 1) FROM t', [$table]);
        $localQuery = $local->field(0)->expression;
        $outerQuery = $outer->field(0)->expression;
        self::assertInstanceOf(ScalarSubquery::class, $localQuery);
        self::assertInstanceOf(ScalarSubquery::class, $outerQuery);

        self::assertFalse((new MergeDependencies())->external($localQuery->query));
        self::assertTrue((new MergeDependencies())->external($outerQuery->query));
    }

    public function testSeparateRetainsAnUnresolvedSubqueryConservatively(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT (SELECT id FROM t)');
        $expression = $operation->field(0)->expression;
        self::assertNotNull($expression);

        self::assertTrue((new MergeDependencies())->separate([\SqlSemantics\Platform\MySql\Construction\Occurrences::fresh($expression)], GrammarRelease::MySql847));
    }
}
