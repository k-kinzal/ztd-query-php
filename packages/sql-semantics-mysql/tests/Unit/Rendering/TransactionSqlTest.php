<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rendering\TransactionSql;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Savepoint;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TransactionSql::class)]
#[Small]
final class TransactionSqlTest extends TestCase
{
    public function testToStringReconstructsDecodedSavepointNames(): void
    {
        self::assertSame('SAVEPOINT `s p`', (new Savepoint(new Name('s p')))->toString());
    }

    public function testProfileRetainsTheSelectedLanguage(): void
    {
        $profile = new LanguageProfile(GrammarRelease::MySql5651);
        self::assertSame($profile, (new Begin(profile: $profile))->profile());
    }

    public function testDeclarationsIntroducesNoSchemaObjects(): void
    {
        self::assertSame([], (new Begin())->declarations());
    }
}
