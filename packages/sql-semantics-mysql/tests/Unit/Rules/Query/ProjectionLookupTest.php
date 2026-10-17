<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\ProjectionLookup;
use SqlSemantics\Platform\MySql\Statement\Name\AliasRule;
use SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\ProjectionScope;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Reference\Missing\SessionState;

#[CoversClass(ProjectionLookup::class)]
#[Medium]
final class ProjectionLookupTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<AliasRule>}>
     */
    public static function providerFind(): iterable
    {
        yield 'prior input alias' => ['SELECT a AS x, (SELECT x) FROM t', []];
        yield 'prior expression alias' => ['SELECT a+1 AS x, (SELECT SUM(x)) FROM t', []];
        yield 'forward reference' => ['SELECT (SELECT x), a AS x FROM t', [AliasRule::Forward]];
        yield 'self reference' => ['SELECT (SELECT x) AS x FROM t', [AliasRule::Forward]];
        yield 'aggregate reference' => ['SELECT SUM(a) AS x, (SELECT x) FROM t', [AliasRule::Aggregate]];
        yield 'outer-owned aggregate' => ['SELECT (SELECT SUM(t.a)) AS x, (SELECT x) FROM t', [AliasRule::Aggregate]];
        yield 'independent aggregate' => ['SELECT (SELECT SUM(1)) AS x, (SELECT x) FROM t', []];
        yield 'ambiguous prior columns' => ['SELECT a AS x, b AS x, (SELECT x) FROM t', [AliasRule::Ambiguous]];
        yield 'identical prior columns' => ['SELECT a AS x, a AS x, (SELECT x) FROM t', []];
        yield 'unresolved duplicate' => ['SELECT a AS x, (SELECT x), a AS x FROM t', [AliasRule::Ambiguous]];
        yield 'computed name takes precedence' => ['SELECT a+1 AS x, (SELECT SUM(x)), b AS x FROM t', []];
    }

    /**
     * @param list<AliasRule> $expected
     */
    #[DataProvider('providerFind')]
    public function testFindUsesDeclarationOrderAndAggregateOwnership(string $sql, array $expected): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t(a INT,b INT)');
        $operation = $semantics->analyze($sql, [$table]);

        self::assertSame($expected, array_map(static fn (Diagnostic $problem): ?AliasRule => $problem instanceof InvalidProjectionAlias ? $problem->rule : null, $operation->facts->diagnostics));
    }

    public function testChosenRetainsTheFieldAndEnclosingDepth(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1 AS x');
        $field = $operation->field('x');
        self::assertNotNull($field->name);
        self::assertNotNull($field->expression);
        $projection = new ProjectionScope([0 => [$field->name, $field->expression]]);
        $projection->bind(0, $field);
        $scope = new Environment($operation->context, projection: $projection);
        $reference = (new ProjectionLookup())->chosen($scope, new Name('x'), [0], 2);

        self::assertInstanceOf(AliasTarget::class, $reference);
        self::assertSame($field, $reference->field);
        self::assertSame(2, $reference->depth);
        self::assertNull((new ProjectionLookup())->find($scope, new Name('x'), 0));
    }

    public function testFindKeepsAnUnknownResultNameConditional(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT 1');
        $expression = $operation->field(0)->expression;
        self::assertNotNull($expression);
        $missing = new SessionState('character_set_client');
        $scope = new Environment($operation->context, projection: new ProjectionScope([[$missing, $expression]]));
        $reference = (new ProjectionLookup())->find($scope, new Name('x'), 1);

        self::assertInstanceOf(ConditionalColumn::class, $reference);
        self::assertSame([$missing], $reference->missing);
    }
}
