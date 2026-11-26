<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Header;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderCookie;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderInteger;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderPragma;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderReadField;

#[CoversClass(HeaderPragma::class)]
#[Small]
final class HeaderPragmaTest extends TestCase
{
    public function testProfileIdIdentifiesThePinnedLanguage(): void
    {
        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', (new HeaderPragma(HeaderCookie::UserVersion))->profileId());
    }

    public function testToStringDistinguishesReadingFromZeroAndSignedWrites(): void
    {
        $schema = new Identifier('archive "q"');

        self::assertSame('PRAGMA "main".data_version', (new HeaderPragma(HeaderReadField::DataVersion))->toString());
        self::assertSame('PRAGMA "archive ""q""".application_id(0)', (new HeaderPragma(HeaderCookie::ApplicationId, $schema, new HeaderInteger(0)))->toString());
        self::assertSame('PRAGMA "archive ""q""".user_version(-2147483648)', (new HeaderPragma(HeaderCookie::UserVersion, $schema, new HeaderInteger(-2147483648)))->toString());
    }
}
