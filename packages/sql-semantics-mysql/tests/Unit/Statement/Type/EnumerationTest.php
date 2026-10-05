<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\Enumeration;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\EnumerationKind;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Enumeration::class)]
#[Small]
final class EnumerationTest extends TestCase
{
    public function testNameSpellsEnumOrSet(): void
    {
        self::assertSame('ENUM', (new Enumeration(EnumerationKind::Enum, [new Text('a')]))->name());
        self::assertSame('SET', (new Enumeration(EnumerationKind::Set, [new Text('a'), new Text('b')]))->name());
    }

    public function testRenderWritesTheMembersInDeclarationOrderAndTheCharacterSetAttribute(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Enumeration(EnumerationKind::Set, [new Text('b'), new Text('a')], new CharsetAttribute(CharsetForm::Named, new Name('utf8mb4'), BinaryMark::Trailing)))->render($out);

        self::assertSame("SET('b', 'a') CHARSET utf8mb4 BINARY", (new Lexical())->join($out->pieces()));
    }

    public function testRenderWritesTheMembersAloneWithoutAnAttribute(): void
    {
        $out = new Output(new Codec(GrammarRelease::MySql847));

        (new Enumeration(EnumerationKind::Enum, [new Text('x')]))->render($out);

        self::assertSame("ENUM('x')", (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesASetWithACharacterSetLoweredFromADeclaration(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-8.4.7', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse("CREATE TABLE t (c SET('a','b') CHARACTER SET utf8mb4)")->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Enumeration::class, $type);
        self::assertSame(EnumerationKind::Set, $type->kind);
        self::assertCount(2, $type->members);
        self::assertSame(['a', 'b'], [$type->members[0]->value, $type->members[1]->value]);
        self::assertSame('utf8mb4', $type->charset?->charset?->value);
        $type->render($out);
        self::assertSame("SET('a', 'b') CHARACTER SET utf8mb4", (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesAnEnumWithTheBinaryAttributeUnderTheOldestRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.6.51', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse("CREATE TABLE t (c ENUM('x') BINARY)")->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Enumeration::class, $type);
        self::assertSame(EnumerationKind::Enum, $type->kind);
        self::assertSame('x', $type->members[0]->value);
        self::assertSame(CharsetForm::Binary, $type->charset?->form);
        $type->render($out);
        self::assertSame("ENUM('x') BINARY", (new Lexical())->join($out->pieces()));
    }

    public function testRenderReproducesTheMemberOrderUnderTheMiddleRelease(): void
    {
        $platform = new Platform();
        $profile = $platform->profile('mysql-5.7.44', null, ParameterStyle::Native);
        $node = $platform->parser($profile)->parse("CREATE TABLE t (c ENUM('m', 's', 'l'))")->find('type')[0];
        $type = (new Lowering($platform->productions($profile), new Leaves(), $profile))->types->type($node);
        $out = new Output($platform->codec($profile));

        self::assertInstanceOf(Enumeration::class, $type);
        self::assertSame(['m', 's', 'l'], [$type->members[0]->value, $type->members[1]->value, $type->members[2]->value]);
        self::assertNull($type->charset);
        $type->render($out);
        self::assertSame("ENUM('m', 's', 'l')", (new Lexical())->join($out->pieces()));
    }

    public function testEmptyMemberListIsRejected(): void
    {
        $this->expectExceptionMessage('An enumeration declares at least one member.');

        new Enumeration(EnumerationKind::Enum, []);
    }
}
