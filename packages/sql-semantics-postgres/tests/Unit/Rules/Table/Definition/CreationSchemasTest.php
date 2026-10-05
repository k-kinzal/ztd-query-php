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
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\CreationSchemas;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(CreationSchemas::class)]
#[Small]
final class CreationSchemasTest extends TestCase
{
    public function testCheckReportsATemporaryRelationInAPermanentSchema(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new CreationSchemas())->check($derivation, new QualifiedName(new Name('t'), new Name('public')), Persistence::Temp);
        self::assertSame(['cannot create temporary relation in non-temporary schema'], array_map(static fn ($problem): string => $problem->message(), $derivation->facts()->diagnostics));
    }

    public function testCheckReportsAnUnloggedRelationInTheTemporarySchema(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new CreationSchemas())->check($derivation, new QualifiedName(new Name('t'), new Name('pg_temp')), Persistence::Unlogged);
        self::assertSame(['only temporary relations may be created in temporary schemas'], array_map(static fn ($problem): string => $problem->message(), $derivation->facts()->diagnostics));
    }

    public function testCheckAdmitsMatchingSchemasAndUnqualifiedNames(): void
    {
        $derivation = new Derivation((new Platform())->context(new LanguageProfile(GrammarRelease::PostgreSql172), null, [], true));
        (new CreationSchemas())->check($derivation, new QualifiedName(new Name('t'), new Name('pg_temp')), Persistence::Permanent);
        (new CreationSchemas())->check($derivation, new QualifiedName(new Name('t'), new Name('pg_temp_3')), Persistence::Temporary);
        (new CreationSchemas())->check($derivation, new QualifiedName(new Name('t')), Persistence::Temp);
        (new CreationSchemas())->check($derivation, new QualifiedName(new Name('t'), new Name('public')), Persistence::Unlogged);
        self::assertSame([], $derivation->facts()->diagnostics);
    }
}
