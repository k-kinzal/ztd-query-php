<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectForms;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\LargeObjectNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\OperatorGroupName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TransformFor;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\TypeReference;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ObjectForms::class)]
#[Small]
final class ObjectFormsTest extends TestCase
{
    public function testFormNamesEachReference(): void
    {
        $forms = new ObjectForms();
        self::assertSame(
            ['dotted', 'name', 'member', 'relation-member', 'domain-member', 'type', 'cast', 'transform', 'group', 'large', 'relation', 'only-relation'],
            [
                $forms->form(new DottedName([new Name('a')])),
                $forms->form(new UnqualifiedName(new Name('a'))),
                $forms->form(new MemberName(new Name('a'), new DottedName([new Name('t')]))),
                $forms->form(new MemberName(new Name('a'), new QualifiedName(new Name('t')))),
                $forms->form(new MemberName(new Name('a'), new DottedName([new Name('d')]), true)),
                $forms->form(new TypeReference(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))))),
                $forms->form(new CastPair(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))))),
                $forms->form(new TransformFor(new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new Name('l'))),
                $forms->form(new OperatorGroupName(new DottedName([new Name('c')]), new Name('btree'))),
                $forms->form(new LargeObjectNumber(new SignedNumber(false, new IntegerConstant('1')))),
                $forms->form(new RelationTarget(new RelationReference(new QualifiedName(new Name('t'))))),
                $forms->form(new RelationTarget(new RelationReference(new QualifiedName(new Name('t')), true))),
            ],
        );
    }

    public function testDropFollowsTheKindNonterminals(): void
    {
        $forms = new ObjectForms();
        self::assertSame([['dotted'], ['name'], ['member'], ['routine'], ['type']], [$forms->drop(ObjectKind::Statistics), $forms->drop(ObjectKind::AccessMethod), $forms->drop(ObjectKind::Rule), $forms->drop(ObjectKind::Procedure), $forms->drop(ObjectKind::Domain)]);
    }

    public function testCommentAddsColumnsConstraintsAndLargeObjects(): void
    {
        $forms = new ObjectForms();
        self::assertSame([['dotted'], ['member'], ['domain-member'], ['large'], ['name']], [$forms->comment(ObjectKind::Column), $forms->comment(ObjectKind::Constraint), $forms->comment(ObjectKind::DomainConstraint), $forms->comment(ObjectKind::LargeObject), $forms->comment(ObjectKind::Role)]);
    }

    public function testLabelAcceptsFewerKinds(): void
    {
        $forms = new ObjectForms();
        self::assertSame([['aggregate'], ['type'], [], []], [$forms->label(ObjectKind::Aggregate), $forms->label(ObjectKind::Domain), $forms->label(ObjectKind::Operator), $forms->label(ObjectKind::Cast)]);
    }

    public function testCommonCoversTheSignedKinds(): void
    {
        $forms = new ObjectForms();
        self::assertSame([['operator'], ['group'], ['cast'], ['transform'], []], [$forms->common(ObjectKind::Operator), $forms->common(ObjectKind::OperatorFamily), $forms->common(ObjectKind::Cast), $forms->common(ObjectKind::Transform), $forms->common(ObjectKind::Table)]);
    }

    public function testAlteredDependsOnTheCommand(): void
    {
        $forms = new ObjectForms();
        self::assertSame(
            [['name'], [], ['dotted'], ['relation', 'only-relation'], ['name']],
            [$forms->altered(ObjectKind::Extension, 'schema'), $forms->altered(ObjectKind::Extension, 'owner'), $forms->altered(ObjectKind::TextSearchParser, 'rename'), $forms->altered(ObjectKind::Table, 'rename'), $forms->altered(ObjectKind::Role, 'rename')],
        );
    }

    public function testAlteredRestCoversRelationsOperatorsMembersAndLargeObjects(): void
    {
        $forms = new ObjectForms();
        self::assertSame(
            [['relation'], [], ['relation-member'], ['operator'], ['large'], []],
            [$forms->alteredRest(ObjectKind::Index, 'rename'), $forms->alteredRest(ObjectKind::Index, 'schema'), $forms->alteredRest(ObjectKind::Policy, 'rename'), $forms->alteredRest(ObjectKind::Operator, 'owner'), $forms->alteredRest(ObjectKind::LargeObject, 'owner'), $forms->alteredRest(ObjectKind::Table, 'owner')],
        );
    }

    public function testDependsAcceptsSixKinds(): void
    {
        $forms = new ObjectForms();
        self::assertSame([['routine'], ['relation-member'], ['relation'], []], [$forms->depends(ObjectKind::Routine), $forms->depends(ObjectKind::Trigger), $forms->depends(ObjectKind::MaterializedView), $forms->depends(ObjectKind::Table)]);
    }
}
