<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\Binary;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\SpatialKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Spatial;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Type\TypeDescriptor;

#[CoversNothing]
#[Small]
final class TypeNameTest extends TestCase
{
    public function testNameIsTheDescriptorNameOfEveryTypeClass(): void
    {
        $types = [
            new Integral(IntegralKind::Int, '11'),
            new Decimal('10', '2'),
            new Floating(FloatingKind::Real),
            new Elementary(ElementaryKind::Bit, '8'),
            new Temporal(TemporalKind::DateTime, '6'),
            new Spatial(SpatialKind::Point),
            new Character(CharacterKind::VarChar, '3', true),
            new Binary(BinaryKind::LongVarBinary),
            new Enumeration(EnumerationKind::Set, [new Text('a')]),
            new CastTarget(CastKind::Unsigned),
        ];

        self::assertContainsOnlyInstancesOf(TypeName::class, $types);
        self::assertContainsOnlyInstancesOf(TypeDescriptor::class, $types);
        self::assertContainsOnlyInstancesOf(Node::class, $types);
        self::assertSame('INT', $types[0]->name());
        self::assertSame('DECIMAL', $types[1]->name());
        self::assertSame('REAL', $types[2]->name());
        self::assertSame('BIT', $types[3]->name());
        self::assertSame('DATETIME', $types[4]->name());
        self::assertSame('POINT', $types[5]->name());
        self::assertSame('NVARCHAR', $types[6]->name());
        self::assertSame('LONG VARBINARY', $types[7]->name());
        self::assertSame('SET', $types[8]->name());
        self::assertSame('UNSIGNED', $types[9]->name());
    }

    public function testRenderOfEveryLoweredDeclaredTypeIsATypeName(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $parser = $platform->parser($profile);
        $lowering = new Lowering($platform->productions($profile), new Leaves(), $profile);
        $declared = $lowering->types->type($parser->parse('CREATE TABLE t (c NATIONAL CHAR(3) BINARY)')->find('type')[0]);
        $cast = $lowering->types->castTarget($parser->parse('SELECT CAST(a AS DECIMAL(10,2))')->find('cast_type')[0]);

        self::assertSame('NCHAR', $declared->name());
        self::assertSame('DECIMAL', $cast->name());
    }
}
