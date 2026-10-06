<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Query\DerivedShapes;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\NameConversion;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(DerivedShapes::class)]
#[Medium]
final class DerivedShapesTest extends TestCase
{
    public function testShapeTakesTheSlotsOfTheQuery(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        $fact = new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::NotNull)), new Field(1, new OutputSlot(null, new Known(new Integral(IntegralKind::Int)), Nullability::NotNull, null, null, [new NameConversion('latin2')]))], $semantics->context()->columnNames);
        $plain = (new DerivedShapes())->shape($fact, [], $derivation);
        $listed = (new DerivedShapes())->shape($fact, [new Name('x'), new Name('y')], $derivation);

        self::assertCount(2, $plain->slots);
        self::assertTrue($plain->complete());
        self::assertEquals([new NameConversion('latin2')], $plain->slots[1]->unnamed);
        self::assertSame(['x', 'y'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $listed->slots));
        self::assertTrue($listed->complete());
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testUniqueReportsADuplicateName(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $derivation = new Derivation($semantics->context());
        (new DerivedShapes())->unique([new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::NotNull), new OutputSlot(new Name('A'), new Known(new Integral(IntegralKind::Int)), Nullability::NotNull)], $derivation);

        self::assertCount(1, $derivation->facts()->diagnostics);
        self::assertInstanceOf(Misuse::class, $derivation->facts()->diagnostics[0]);
        self::assertSame(MisuseRule::DuplicateColumn, $derivation->facts()->diagnostics[0]->rule);
    }
}
