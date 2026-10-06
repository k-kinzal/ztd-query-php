<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\IdentityColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\NamedOnPath;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredDomain;

#[CoversClass(IdentityColumns::class)]
#[Small]
final class IdentityColumnsTest extends TestCase
{
    public function testCheckReportsATypeOtherThanTheThreeIntegerTypes(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new IdentityColumns())->check($derivation, Builtin::Text);
        (new IdentityColumns())->check($derivation, new ArrayOf(Builtin::Int4));
        self::assertSame(['identity column type must be smallint, integer, or bigint', 'identity column type must be smallint, integer, or bigint'], array_map(static fn ($problem): string => $problem->message(), $derivation->facts()->diagnostics));
    }

    public function testCheckAdmitsTheIntegerTypesAndTypesKnownByName(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new IdentityColumns())->check($derivation, Builtin::Int2);
        (new IdentityColumns())->check($derivation, Builtin::Int8);
        (new IdentityColumns())->check($derivation, new NamedOnPath(new QualifiedName(new Name('d')), [new UndeclaredDomain(new QualifiedName(new Name('d')))]));
        (new IdentityColumns())->check($derivation, null);
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
