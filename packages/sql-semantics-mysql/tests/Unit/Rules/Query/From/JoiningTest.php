<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Resolution\VisibleRelation;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\Field;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\OutputSlot;
use SqlSemantics\Platform\MySql\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Known;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Rules\Query\From\Joining;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(Joining::class)]
#[Medium]
final class JoiningTest extends TestCase
{
    public function testJoinMergesTheUsingColumns(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->diagnose('SELECT * FROM t JOIN u USING (a)', [$t, $u]);

        self::assertSame(['a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
    }

    public function testJoinOrdersMergedKeysByThePreservedHeading(): void
    {
        $mysql = new Inspection();
        $left = $mysql->inspect('CREATE TABLE app.first_input (id INT, lane INT, first_val INT)');
        $right = $mysql->inspect('CREATE TABLE app.second_input (lane INT, last_val INT, id INT)');
        $declarations = [$left, $right];
        $from = 'FROM app.first_input AS a';
        $using = $mysql->diagnose("SELECT * $from JOIN app.second_input b USING (lane, id)", $declarations);
        $rightMerge = $mysql->diagnose("SELECT * $from NATURAL RIGHT JOIN app.second_input b", $declarations);
        $ordinary = $mysql->diagnose("SELECT * $from RIGHT JOIN app.second_input b ON a.id = b.id", $declarations);
        $names = static fn (InspectedStatement $query): array => array_map(static fn (Field $field): ?string => $field->name?->value, $query->fields()->items ?? []);

        self::assertSame(['id', 'lane', 'first_val', 'last_val'], $names($using));
        self::assertSame(['lane', 'id', 'last_val', 'first_val'], $names($rightMerge));
        self::assertSame(['id', 'lane', 'first_val', 'lane', 'last_val', 'id'], $names($ordinary));
    }

    public function testNamesAnswersNothingForANaturalJoinOverUndeclaredColumns(): void
    {
        $operation = (new Inspection())->diagnose('SELECT * FROM t NATURAL JOIN u');

        self::assertNull($operation->fields());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testLocateReportsAColumnSelectedTwice(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull)]);
        $operation = $semantics->diagnose('SELECT 1 FROM (t AS x, t AS y) JOIN t USING (a)', [$t]);

        self::assertCount(1, $operation->facts->diagnostics);
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::AmbiguousJoinColumn, $operation->facts->diagnostics[0]->rule);
    }

    public function testExtendMakesEveryColumnNullable(): void
    {
        $slot = new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::NotNull);
        $extended = (new Joining())->extend(new VisibleRelation(new Dual(), new RowShape([$slot])));

        self::assertSame(Nullability::Nullable, $extended->shape->slots[0]->nullability);
        self::assertSame($slot, $extended->shape->slots[0]->origin);
    }

    public function testNullableKeepsANullableSlot(): void
    {
        $slot = new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::Nullable);

        self::assertSame($slot, (new Joining())->nullable($slot));
    }

    public function testNullableKeepsTheInputsAnUnnamedSlotDependsOn(): void
    {
        $slot = new OutputSlot(null, new Known(new Integral(IntegralKind::Int)), Nullability::NotNull, null, null, [new SessionState('character_set_client')]);

        self::assertEquals([new SessionState('character_set_client')], (new Joining())->nullable($slot)->unnamed);
    }

    public function testLocateLeavesAUsingColumnAnUnnamedColumnMayBe(): void
    {
        $semantics = new Inspection();
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('x'), new Integral(IntegralKind::Int), Nullability::NotNull)]);

        self::assertSame([], $semantics->diagnose("SELECT x FROM (SELECT 'é') AS d JOIN t USING (x)", [$t])->facts->diagnostics);
    }
}
