<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rules\Query\From\Joining;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Resolution\ImplicitSlot;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Joining::class)]
#[Medium]
final class JoiningTest extends TestCase
{
    public function testJoinMergesTheUsingColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('b'), new Integral(IntegralKind::BigInt), Nullability::Nullable),
        ]);
        $u = new Table(new QualifiedName(new Name('u'), new Name('(current)')), $semantics->profile(), [
            new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull),
            new Column(new Name('c'), new Integral(IntegralKind::Int), Nullability::NotNull),
        ]);
        $operation = $semantics->analyze('SELECT * FROM t JOIN u USING (a)', [$t, $u]);

        self::assertSame(['a', 'b', 'c'], array_map(static fn (Field $field): ?string => $field->name?->value, $operation->fields()->items ?? []));
    }

    public function testNamesAnswersNothingForANaturalJoinOverUndeclaredColumns(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM t NATURAL JOIN u');

        self::assertNull($operation->fields());
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testLocateReportsAColumnSelectedTwice(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('a'), new Integral(IntegralKind::Int), Nullability::NotNull)]);
        $operation = $semantics->analyze('SELECT 1 FROM (t AS x, t AS y) JOIN t USING (a)', [$t]);

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
        $semantics = new Semantics(Dialect::MySql);
        $t = new Table(new QualifiedName(new Name('t'), new Name('(current)')), $semantics->profile(), [new Column(new Name('x'), new Integral(IntegralKind::Int), Nullability::NotNull)]);

        self::assertSame([], $semantics->analyze("SELECT x FROM (SELECT 'é') AS d JOIN t USING (x)", [$t])->facts->diagnostics);
    }

    public function testLocateNamesTheAmbiguousColumnAsItsRelationNamesIt(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT * FROM ((SELECT 1 a) d JOIN (SELECT 2 a) e ON 1) JOIN (SELECT 3 a) f USING (A)');

        self::assertEquals([new Misuse(MisuseRule::AmbiguousJoinColumn, new Name('a'))], $operation->facts->diagnostics);
    }

    public function testJoinSelectsAnInvisibleUsingColumnAmongTheMergedColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = [...$semantics->analyze('CREATE TABLE v (a INT, e INT INVISIBLE, f INT)')->declarations(), ...$semantics->analyze('CREATE TABLE u (e INT, b INT)')->declarations(), ...$semantics->analyze('CREATE TABLE x (e INT INVISIBLE, c INT)')->declarations()];
        $names = static fn (string $sql): array => array_map(static fn (Field $field): ?string => $field->name?->value, $semantics->analyze($sql, $tables)->fields()->items ?? []);

        self::assertSame(['e', 'a', 'f', 'b'], $names('SELECT * FROM v JOIN u USING (e)'));
        self::assertSame(['e', 'a', 'f', 'c'], $names('SELECT * FROM v JOIN x USING (e)'));
        self::assertSame(['a', 'f'], $names('SELECT v.* FROM v JOIN u USING (e)'));
        self::assertSame(['e', 'a', 'f', 'c', 'b'], $names('SELECT * FROM v JOIN x USING (e) JOIN u USING (e)'));
        self::assertSame([], $semantics->analyze('SELECT e, x.e FROM v JOIN x USING (e)', $tables)->facts->diagnostics);
    }

    public function testDeclaredLeavesOutTheInvisibleColumnsAnOperandOfANaturalJoinMerged(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tables = [...$semantics->analyze('CREATE TABLE v (a INT, e INT INVISIBLE, f INT)')->declarations(), ...$semantics->analyze('CREATE TABLE u (e INT, b INT)')->declarations(), ...$semantics->analyze('CREATE TABLE x (e INT INVISIBLE, c INT)')->declarations()];
        $names = static fn (string $sql): array => array_map(static fn (Field $field): ?string => $field->name?->value, $semantics->analyze($sql, $tables)->fields()->items ?? []);

        self::assertSame(['a', 'f', 'c', 'e', 'b'], $names('SELECT * FROM (v JOIN x USING (e)) NATURAL JOIN u'));
        self::assertSame(['a', 'f', 'e', 'b'], $names('SELECT * FROM v NATURAL JOIN u'));
    }

    public function testRevealAddsTheInvisibleColumnAUsingNameDenotes(): void
    {
        $platform = new Platform();
        $hidden = new OutputSlot(new Name('e'), new Known(new Integral(IntegralKind::Int)), Nullability::Nullable);
        $relation = new VisibleRelation(new Dual(), new RowShape([new OutputSlot(new Name('a'), new Known(new Integral(IntegralKind::Int)), Nullability::Nullable)]), null, null, [], [new ImplicitSlot([new Name('e')], $hidden)]);
        $derivation = new Derivation($platform->context($platform->profile(null, null, ParameterStyle::Native), null, [], false));

        self::assertSame([[0, 0], [0, 1]], (new Joining())->reveal($derivation, [$relation], [[0, 0]], new Name('E'), [0, 1]));
        self::assertSame([[0, 0]], (new Joining())->reveal($derivation, [$relation], [[0, 0]], new Name('a'), [0, 1]));
    }
}
