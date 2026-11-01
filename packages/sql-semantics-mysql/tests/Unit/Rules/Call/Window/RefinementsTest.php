<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Refinements;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Refinements::class)]
#[Small]
final class RefinementsTest extends TestCase
{
    public function testConditionDistinguishesDependentPartitionsOrderingAndFrames(): void
    {
        $rules = new Refinements();
        $item = new M\Query\OrderItem(new M\Literal\NumberLiteral('1.1'));
        self::assertSame(M\Call\Problem\WindowRefinementKind::Partition, $rules->condition(new M\Call\Window\WindowSpec(new Name('w'), partition: [$item]), []));
        $base = new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec(order: [$item]));
        self::assertSame(M\Call\Problem\WindowRefinementKind::Ordering, $rules->condition(new M\Call\Window\WindowSpec(new Name('w'), order: [$item]), [$base]));
        $frame = new M\Call\Window\Frame(M\Call\Window\FrameUnit::Rows, new M\Call\Window\FrameBound(M\Call\Window\FrameBoundKind::CurrentRow));
        $framed = new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec(frame: $frame));
        self::assertSame(M\Call\Problem\WindowRefinementKind::Frame, $rules->condition(new M\Call\Window\WindowSpec(new Name('w')), [$framed]));
        self::assertNull($rules->condition(new M\Call\Window\WindowSpec(new Name('w')), [$base]));
        self::assertNull($rules->condition(new M\Call\Window\WindowSpec(partition: [$item]), []));
    }

    public function testConnectedRequiresTheActualLexicalAncestorChain(): void
    {
        $root = new M\Call\Window\WindowSpec(new Name('w'));
        $one = new M\Query\Clause\WindowDefinition(new Name('W'), new M\Call\Window\WindowSpec(new Name('v')));
        $two = new M\Query\Clause\WindowDefinition(new Name('v'), new M\Call\Window\WindowSpec());
        self::assertTrue((new Refinements())->connected($root, [$one, $two]));
        self::assertFalse((new Refinements())->connected($root, [$two, $one]));
        self::assertFalse((new Refinements())->connected($root, [$one, $one]));
    }
    public function testOrderedIdentifiesTheActualAncestorInsteadOfItsDependentAlias(): void
    {
        $parent = new M\Query\Clause\WindowDefinition(new Name('v'), new M\Call\Window\WindowSpec(new Name('w')));
        $base = new M\Query\Clause\WindowDefinition(new Name('w'), new M\Call\Window\WindowSpec(order: [new M\Query\OrderItem(new M\Literal\NumberLiteral('1.1'))]));
        self::assertSame($base, (new Refinements())->ordered([$parent, $base]));
        self::assertNull((new Refinements())->ordered([$parent]));
    }
}
