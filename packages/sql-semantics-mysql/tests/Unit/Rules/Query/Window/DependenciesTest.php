<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Window\Dependencies;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Dependencies::class)]
#[Small]
final class DependenciesTest extends TestCase
{
    public function testProblemRetainsAnUnusedDefinitionsTransitiveOrderingConflict(): void
    {
        $first = new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec(order: [new M\Query\OrderItem(new M\Literal\NumberLiteral('1.1'))]));
        $second = new M\Query\Clause\WindowDefinition(new Name('v'), new M\Call\Window\WindowSpec(new Name('w')));
        $third = new M\Query\Clause\WindowDefinition(new Name('u'), new M\Call\Window\WindowSpec(new Name('v'), order: [new M\Query\OrderItem(new M\Literal\NumberLiteral('2.2'))]));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], windows: [$first, $second, $third]);
        $problem = (new Dependencies())->problem($query);
        self::assertInstanceOf(M\Call\Problem\WindowRefinement::class, $problem);
        self::assertSame([$second, $first], $problem->ancestors);
        self::assertSame($third->specification, $problem->window);
        self::assertSame('u', $problem->name?->value);
        self::assertSame($query, (new M\Routine\Program\Selection\DeferredQuery($query))->query);
    }

    public function testProblemAllowsIndependentOrderingAndInheritedPartitions(): void
    {
        $base = new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec(partition: [new M\Query\OrderItem(new M\Literal\NumberLiteral('1.1'))]));
        $child = new M\Query\Clause\WindowDefinition(new Name('v'), new M\Call\Window\WindowSpec(new Name('w'), order: [new M\Query\OrderItem(new M\Literal\NumberLiteral('2.2'))]));
        $query = new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))], windows: [$base, $child]);
        self::assertNull((new Dependencies())->problem($query));
        self::assertSame($query, (new M\QueryRoot($query))->query);
    }

    public function testAncestorsStopsAtAMissingDefinitionOrCycle(): void
    {
        $base = new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec(new Name('v')));
        $loop = new M\Query\Clause\WindowDefinition(new Name('v'), new M\Call\Window\WindowSpec(new Name('w')));
        $window = new M\Call\Window\WindowSpec(new Name('w'));
        self::assertSame([$base], (new Dependencies())->ancestors($window, ['w' => $base]));
        self::assertSame([$base, $loop], (new Dependencies())->ancestors($window, ['w' => $base, 'v' => $loop]));
    }
}
