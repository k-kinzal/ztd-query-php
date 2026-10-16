<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Constraint;

use MySqlMemory\Command\Definition\Constraint\ColumnPrefixes;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ColumnPrefixes::class)]
#[Small]
final class ColumnPrefixesTest extends TestCase
{
    public function testLengthRequiresAStringColumnBeforeInspectingItsRows(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1089);
        (new ColumnPrefixes())->length(new ColumnPart(new Name('a'), new Numeral('2')), Domain::integer(), IndexKind::Unique);
    }

    public function testLengthIgnoresAPositiveFullTextPrefix(): void
    {
        self::assertNull((new ColumnPrefixes())->length(new ColumnPart(new Name('a'), new Numeral('2')), Domain::string(1, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary()), IndexKind::FullText));
    }

    public function testLengthLeavesSpatialConditionsToTheSpatialIndex(): void
    {
        self::assertSame(2, (new ColumnPrefixes())->length(new ColumnPart(new Name('a'), new Numeral('2')), Domain::integer(), IndexKind::Spatial));
    }

    public function testLengthRefusesAPrefixBeyondTheCurrentStringCapacity(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1089);
        (new ColumnPrefixes())->length(new ColumnPart(new Name('a'), new Numeral('2')), Domain::string(1, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary()), IndexKind::Index);
    }
}
