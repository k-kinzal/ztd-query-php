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

    public function testOrdinaryIndexExcludesMultipleSpatialParts(): void
    {
        $this->expectExceptionMessage('An ordinary index request requires a valid name and key operands compatible with its kind.');
        new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('b'))], M\Table\Key\IndexKind::Spatial);
    }

    public function testAlterRetainsMultipleSpatialParts(): void
    {
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Spatial, [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('b'))]);
        self::assertTrue((new M\Alter\AlterTable(new QualifiedName(new Name('t')), [new M\Alter\Command\AddConstraint($index)]))->hasExecutionCondition());
    }

    public function testCreationRetainsMultipleSpatialParts(): void
    {
        $index = new M\Table\Key\IndexDefinition(M\Table\Key\IndexKind::Spatial, [new M\Table\Key\ColumnPart(new Name('a')), new M\Table\Key\ColumnPart(new Name('b'))]);
        $column = new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('a')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int)));
        self::assertTrue((new M\Table\CreateTable(new QualifiedName(new Name('t')), [$column, new M\Table\Column\ColumnDefinition(new M\Name\ColumnName(new Name('b')), new M\Table\Column\OrdinaryColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int))), $index]))->hasExecutionCondition());
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

    public function testOrdinaryIndexExcludesAFunctionalSpatialOperand(): void
    {
        $this->expectExceptionMessage('An ordinary index request requires a valid name and key operands compatible with its kind.');
        new M\Table\CreateIndex(new Name('i'), new QualifiedName(new Name('t')), [new M\Table\Key\ExpressionPart(new M\Literal\NumberLiteral('1'))], M\Table\Key\IndexKind::Spatial);
    }

}
