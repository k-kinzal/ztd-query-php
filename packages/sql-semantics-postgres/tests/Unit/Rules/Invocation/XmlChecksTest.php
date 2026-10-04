<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Invocation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\XmlChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\XmlProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Xml\XmlAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Parameterized;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Type\Known;

#[CoversClass(XmlChecks::class)]
#[Small]
final class XmlChecksTest extends TestCase
{
    public function testAttributesReportsUnnamedValuesAndRepeatedNames(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new XmlChecks())->attributes($derivation, [new XmlAttribute(new Constant(new IntegerConstant('1'))), new XmlAttribute(new ColumnReference([new Name('a')])), new XmlAttribute(new Constant(new IntegerConstant('1')), new Name('a'))]);
        self::assertEquals([new XmlProblem(XmlProblemKind::UnnamedAttribute), new XmlProblem(XmlProblemKind::RepeatedAttribute, 'a')], $derivation->facts()->diagnostics);
    }

    public function testElementsReportsUnnamedValues(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new XmlChecks())->elements($derivation, [new XmlAttribute(new ColumnReference([new Name('a')])), new XmlAttribute(new Constant(new IntegerConstant('1')))]);
        self::assertEquals([new XmlProblem(XmlProblemKind::UnnamedElement)], $derivation->facts()->diagnostics);
    }

    public function testSerializedAcceptsCharacterTypes(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        $type = new Known(new Parameterized(Builtin::Varchar, 10));
        self::assertSame($type, (new XmlChecks())->serialized($derivation, $type));
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
