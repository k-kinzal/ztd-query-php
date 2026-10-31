<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\Index\Shapes;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(Shapes::class)]
#[Medium]
final class ShapesTest extends TestCase
{
    /**
     * @return iterable<string, array{M\Table\Key\IndexKind, int, bool}>
     */
    public static function providerKinds(): iterable
    {
        foreach (M\Table\Key\IndexKind::cases() as $kind) {
            foreach ([1, 2, 3] as $count) {
                yield $kind->value . ':' . $count => [$kind, $count, $kind === M\Table\Key\IndexKind::Spatial && $count > 1];
            }
        }
    }

    #[DataProvider('providerKinds')]
    public function testProblemResolvesTheIndexKindAndOwnsItsFirstOperand(M\Table\Key\IndexKind $kind, int $count, bool $refused): void
    {
        $parts = array_map(static fn (int $number): M\Table\Key\ColumnPart => new M\Table\Key\ColumnPart(new Name('c' . $number)), range(1, $count));
        $problem = (new Shapes())->problem($kind, $parts);
        self::assertSame($refused, $problem !== null);
        self::assertSame($refused ? $parts[0] : null, $problem?->owner);
    }

    public function testRequestRetainsMultipleSpatialParts(): void
    {
        $request = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('b'))], M\Table\Key\IndexKind::Spatial);
        self::assertTrue($request->hasExecutionCondition());
    }

    public function testAlterRetainsMultipleSpatialParts(): void
    {
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Spatial, [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('b'))]);
        self::assertTrue((new M\Alter\AlterTable(new QualifiedName(new Name('t')), [new M\Alter\Command\AddConstraint($index)]))->hasExecutionCondition());
    }

    public function testCreationRefusesMultipleSpatialPartsAsAWrittenProblem(): void
    {
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Spatial, [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('b'))]);
        $column = static fn (string $name): M\Table\Column\ColumnDefinition => new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name($name)), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));

        self::assertInstanceOf(M\Table\Problem\Creation\SpatialKeyArity::class, (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems())->problem([$column('a'), $column('b'), $index], null, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }
    /**
     * @return iterable<string, array{M\Table\Key\IndexKind, bool}>
     */
    public static function providerFunctionalKinds(): iterable
    {
        foreach (M\Table\Key\IndexKind::cases() as $kind) {
            yield $kind->value => [$kind, in_array($kind, [M\Table\Key\IndexKind::Primary, M\Table\Key\IndexKind::FullText, M\Table\Key\IndexKind::Spatial], true)];
        }
    }

    #[DataProvider('providerFunctionalKinds')]
    public function testProblemDistinguishesFunctionalKindConstraints(M\Table\Key\IndexKind $kind, bool $refused): void
    {
        $part = new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1'));
        $problem = (new Shapes())->problem($kind, [$part]);
        self::assertSame($refused, $problem instanceof M\Table\Problem\Creation\FunctionalIndexKind);
        self::assertSame($refused ? $part : null, $problem?->owner);
    }

    public function testRequestRetainsAFunctionalSpatialOperand(): void
    {
        $request = new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1'))], M\Table\Key\IndexKind::Spatial);
        self::assertTrue($request->hasExecutionCondition());
    }

}
