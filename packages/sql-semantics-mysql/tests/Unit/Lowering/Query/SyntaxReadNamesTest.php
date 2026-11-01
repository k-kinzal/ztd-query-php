<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Query\SyntaxReadNames;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(SyntaxReadNames::class)]
#[Medium]
final class SyntaxReadNamesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}> Lexically reached physical references
     */
    public static function providerScopes(): iterable
    {
        yield 'unused definition' => ['WITH c AS(SELECT 1 FROM unused) SELECT 1 FROM d.t', ['t']];
        yield 'used definition' => ['WITH c AS(SELECT 1 FROM u) SELECT * FROM c', ['u']];
        yield 'earlier definition' => ['WITH c AS(SELECT 1 FROM u), e AS(SELECT * FROM c) SELECT * FROM e', ['u']];
        yield 'later definition is a physical name' => ['WITH c AS(SELECT * FROM e), e AS(SELECT 1 FROM u) SELECT * FROM c', ['e']];
        yield 'recursive self reference' => ['WITH RECURSIVE c AS(SELECT 1 FROM u UNION ALL SELECT 1 FROM c) SELECT * FROM c', ['u']];
        yield 'nested shadowing' => ['WITH c AS(SELECT 1 FROM unused) SELECT * FROM (WITH c AS(SELECT 1 FROM u) SELECT * FROM c) x', ['u']];
        yield 'qualified name bypasses common table' => ['WITH c AS(SELECT 1 FROM unused) SELECT * FROM d.c', ['c']];
        yield 'invalid table identity' => ['SELECT * FROM ``', []];
    }

    /**
     * @param list<string> $expected Reached base names
     */
    #[DataProvider('providerScopes')]
    public function testNamesPreservesLexicalBindingsWhileLoweringIndependentDependencies(string $sql, array $expected): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $names = (new SyntaxReadNames())->names([$platform->parser($profile)->parse($sql)], $lowering, Comparison::Sensitive, strlen($sql));

        self::assertSame($expected, array_map(static fn (QualifiedName $name): string => $name->name->value, $names));
    }

    public function testNamesDoesNotReachADefinitionFromALaterReference(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $sql = 'WITH c AS(SELECT 1 FROM u) SELECT ABS() FROM c';

        self::assertSame([], (new SyntaxReadNames())->names([$platform->parser($profile)->parse($sql)], $lowering, Comparison::Sensitive, 38));
    }

    public function testBindLeavesAnAbsentWithClauseInTheEnclosingScope(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $scopes = [[]];
        $definitions = [];
        $clause = $platform->parser($profile)->parse('UPDATE t SET a=1')->find('opt_with_clause')[0];

        self::assertSame(0, (new SyntaxReadNames())->bind($clause, $lowering, Comparison::Sensitive, $scopes, $definitions, 0));
        self::assertSame([[]], $scopes);
        self::assertSame([], $definitions);
    }

    public function testNamesKeepsTheCompletedTargetBeforeTheRefusalBoundary(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('8.4.7', null, \SqlSemantics\Contract\ParameterStyle::Native);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $node = $platform->parser($profile)->parse('UPDATE t SET a=ABS()')->find('table_ident')[0];

        self::assertSame([], (new SyntaxReadNames())->names([$node], $lowering, Comparison::Sensitive, 7));
        self::assertSame('t', (new SyntaxReadNames())->names([$node], $lowering, Comparison::Sensitive, 8)[0]->name->value);
    }
}
