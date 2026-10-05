<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\SignedNumber;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Attribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\BooleanArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\OperatorAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\NameArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\TextArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Attribute::class)]
#[Small]
final class AttributeTest extends TestCase
{
    public function testValuedTellsWhetherAValueIsWritten(): void
    {
        self::assertSame([false, true, false], [(new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument()))->valued(), (new Attribute(new Name('hashes'), OperatorAttribute::Hashes, new BooleanArgument(new StringConstant('on'))))->valued(), (new Attribute(new Name('colour'), null))->valued()]);
    }

    public function testDeriveClauseDerivesTheValue(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $modifier = new Constant(new IntegerConstant('1'));
        (new Attribute(new Name('function'), OperatorAttribute::Function, new NameArgument(ObjectKind::Function, new TypeName(new NamedDesignation(new DottedName([new Name('f')]), [$modifier])))))->deriveClause($derivation, $derivation->environment());
        self::assertTrue($derivation->facts()->covers($modifier));
    }

    public function testRenderWritesTheNameAsALabelAndTheValue(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new Attribute(new Name('function'), OperatorAttribute::Function, new NameArgument(ObjectKind::Function, new DottedName([new Name('int4eq')]))))->render($out);
        self::assertSame('function = int4eq', (new Lexical())->join($out->pieces()));
    }

    public function testRejectsAMemberOfAnotherName(): void
    {
        $this->expectExceptionMessage('A recognized attribute is the member its name names.');
        new Attribute(new Name('FUNCTION'), OperatorAttribute::Function, new NameArgument(ObjectKind::Function, new DottedName([new Name('f')])));
    }

    public function testRejectsAReadValueOfAnUnrecognizedAttribute(): void
    {
        $this->expectExceptionMessage('A read value belongs to a recognized attribute that reads it that way.');
        new Attribute(new Name('colour'), null, new TextArgument(new StringConstant('red')));
    }

    public function testRejectsAValueReadAnotherWay(): void
    {
        $this->expectExceptionMessage('A read value belongs to a recognized attribute that reads it that way.');
        new Attribute(new Name('leftarg'), OperatorAttribute::Leftarg, new NameArgument(ObjectKind::Function, new DottedName([new Name('int4')])));
    }

    public function testRejectsAWrittenValueTheCommandReads(): void
    {
        $this->expectExceptionMessage('A value the command reads is kept as read.');
        new Attribute(new Name('function'), OperatorAttribute::Function, new StringConstant('int4eq'));
    }

    public function testAcceptsAWrittenValueTheCommandRejects(): void
    {
        $number = new SignedNumber(false, new IntegerConstant('1'));
        self::assertSame($number, (new Attribute(new Name('function'), OperatorAttribute::Function, $number))->value);
    }
}
