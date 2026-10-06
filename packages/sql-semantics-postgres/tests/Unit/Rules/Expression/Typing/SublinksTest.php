<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Sublinks;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\RowArity;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRelation;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Sublinks::class)]
#[Small]
final class SublinksTest extends TestCase
{
    public function testValueIsARowForSeveralColumns(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $slots = [new OutputSlot(new Name('a'), new Known(Builtin::Int4), Nullability::NotNull), new OutputSlot(new Name('b'), new Known(Builtin::Text), Nullability::Nullable)];
        $fact = new QueryFact([new Field(0, $slots[0]), new Field(1, $slots[1])], $derivation->context->columnNames);
        self::assertEquals(new Known(new Composite($slots)), (new Sublinks())->value($derivation, $fact, 'a scalar subquery'));
    }

    public function testArrayIsAnArrayOfTheColumnType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = new QueryFact([new Field(0, new OutputSlot(new Name('a'), new Known(new ArrayOf(Builtin::Int4)), Nullability::NotNull))], $derivation->context->columnNames);
        self::assertEquals(new Known(new ArrayOf(Builtin::Int4)), (new Sublinks())->array($derivation, $fact));
    }

    public function testComparedReportsAWrongNumberOfColumns(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $fact = new QueryFact([], $derivation->context->columnNames);
        self::assertEquals(new Invalid(new RowArity('a subquery comparison', 1, 0)), (new Sublinks())->compared($derivation, new Known(Builtin::Int4), '=', $fact));
    }

    public function testElementsReadsAStringAsAnArrayOfTheValueType(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        self::assertEquals(new Known(Builtin::Bool), (new Sublinks())->elements($derivation, new Known(Builtin::Int4), new OperatorName(new Name('=')), new Known(Builtin::Unknown)));
    }

    public function testSlotsDependOnAnOpenShape(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $missing = new UndeclaredRelation(new QualifiedName(new Name('t')));
        self::assertEquals(new Dependent([$missing]), (new Sublinks())->slots(new QueryFact([new OpenStar([$missing])], $derivation->context->columnNames)));
    }

    public function testArityReportsTheProblem(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new Sublinks())->arity($derivation, 'an ARRAY subquery', 1, 2);
        self::assertEquals([new RowArity('an ARRAY subquery', 1, 2)], $derivation->facts()->diagnostics);
    }
}
