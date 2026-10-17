<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Spatial aggregates consume generated structures and explicitly bound geometry values.
 */
#[CoversNothing]
#[Medium]
final class SpatialAggregateTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, bool, int, int}>
     */
    public static function providerPoints(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                $choices = new Choices('spatial:' . $seed);
                yield $release . ':' . $seed => [$release, $choices->one([false, true]), $choices->one([false, true]), $choices->integer(-100, 100), $choices->integer(-100, 100)];
            }
        }
    }

    /**
     * Duplicate inputs and NULLs retain the same aggregate value in grouped and windowed forms.
     */
    #[DataProvider('providerPoints')]
    public function testGeneratedCoordinatesRetainMultiplicityAndGeometryMetadata(string $release, bool $distinct, bool $window, int $x, int $y): void
    {
        $query = new Select([], [new SelectExpression(new Aggregate(AggregateFunction::Collect, [new ColumnUse(new Name('column_0'))], $distinct, over: $window ? new WindowSpec() : null))], new DerivedTable(new ValuesQuery([new ValueRow([new Parameter()]), new ValueRow([new NullLiteral()]), new ValueRow([new Parameter()])]), new Name('input')));
        $point = Wkb::write(new Geometry(1, [[(float) $x, (float) $y]]));
        $reply = (new Execution())->run($query, $release, [[$point, Domain::string(strlen($point), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())], [$point, Domain::string(strlen($point), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())]]);

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([$window ? Field::Blob : Field::Geometry, 16777216], [$reply->columns[0]->type, $reply->columns[0]->length]);
        self::assertCount($window ? 3 : 1, $reply->rows);
        self::assertNotNull($reply->rows[0][0]);
        $geometry = Wkb::read($reply->rows[0][0]);
        self::assertNotNull($geometry);
        self::assertSame(4, $geometry->type);
        self::assertSame(array_fill(0, $distinct ? 1 : 2, [[(float) $x, (float) $y]]), array_map(static fn (Geometry $member): array => $member->points, $geometry->children));
        self::assertSame(array_fill(0, $window ? 3 : 1, $reply->rows[0]), $reply->rows);
    }

    /**
     * The unrestricted constructor campaign's original counterexample has a complete result type.
     */
    public function testNullSpatialAggregateExecutesWithoutAnUnresolvedType(): void
    {
        $reply = (new Execution())->run(new Aggregate(AggregateFunction::Collect, [new NullLiteral()]));

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[null]], $reply->rows);
        self::assertSame([Field::Geometry, 16777216, 31], [$reply->columns[0]->type, $reply->columns[0]->length, $reply->columns[0]->decimals]);
    }
}
