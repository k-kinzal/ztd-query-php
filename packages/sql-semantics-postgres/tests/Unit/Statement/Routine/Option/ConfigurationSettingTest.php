<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rendering\Codec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Drop;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\ConfigurationSetting;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ConfigurationSetting::class)]
#[Small]
final class ConfigurationSettingTest extends TestCase
{
    public function testAlterableIsTrue(): void
    {
        self::assertTrue((new ConfigurationSetting(new Drop(ObjectKind::Schema, [new UnqualifiedName(new Name('s'))])))->alterable());
    }

    public function testSettingIsNull(): void
    {
        self::assertNull((new ConfigurationSetting(new Drop(ObjectKind::Schema, [new UnqualifiedName(new Name('s'))])))->setting());
    }

    public function testDeriveClauseDerivesTheClauseOnItsOwn(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new ConfigurationSetting(new Drop(ObjectKind::Table, [new DottedName([new Name('t')])])))->deriveClause($derivation, $derivation->environment());
        self::assertSame('Relation t does not exist.', $derivation->facts()->diagnostics[0]->message());
    }

    public function testRenderWritesTheClause(): void
    {
        $out = new Output(new Codec(GrammarRelease::PostgreSql172));
        (new ConfigurationSetting(new Drop(ObjectKind::Schema, [new UnqualifiedName(new Name('s'))])))->render($out);
        self::assertSame('DROP SCHEMA s', (new Lexical())->join($out->pieces()));
    }
}
