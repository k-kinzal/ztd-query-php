<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Server\DatabaseOptions;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation;

#[CoversClass(DatabaseOptions::class)]
#[Medium]
final class DatabaseOptionsTest extends TestCase
{
    public function testDeriveIsNeverReachedForAnUnknownOptionName(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze("CREATE DATABASE d COLLATE 'bad_collation' CHARACTER SET bad_charset");
            self::fail('The collation does not exist.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertEquals(new UnknownCollation('bad_collation'), $failure->problem);
        }
    }

    public function testDeriveIsNeverReachedForACollationUnavailableInTheRelease(): void
    {
        try {
            (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER DATABASE d COLLATE utf8mb4_0900_ai_ci');
            self::fail('The collation is unavailable in 5.7.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertEquals(new UnknownCollation('utf8mb4_0900_ai_ci'), $failure->problem);
        }
    }
    public function testProblemsIndexesAnUnavailableCollationAtItsOption(): void
    {
        $options = [new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset(new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4'))), new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation(new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4_0900_ai_ci')))];

        self::assertEquals([1 => new UnknownCollation('utf8mb4_0900_ai_ci')], (new DatabaseOptions())->problems($options, \SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertSame([], (new DatabaseOptions())->problems($options, \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testInvalidAcceptsConvertedEncryptionAndReadOnlyOptions(): void
    {
        $options = new DatabaseOptions();
        $problem = $options->invalid(new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption(\SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption::Enabled), \SqlSemantics\Contract\GrammarRelease::MySql847);

        self::assertNull($problem);
        self::assertNull($options->invalid(new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseReadOnly(null), \SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testCharsetUsesBothCharsetAndCollationOptions(): void
    {
        $options = new DatabaseOptions();
        $charset = new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset(new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('latin1')));
        $collation = new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation(new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4_bin')));

        self::assertSame('latin1', $options->charset($charset)?->name);
        self::assertSame('utf8mb4', $options->charset($collation)?->name);
    }

    public function testCharsetConflictDistinguishesACharsetDeclarationFromACollation(): void
    {
        $options = new DatabaseOptions();
        $latin1 = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('latin1');
        $utf8 = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4');
        $charset = new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset(new \SqlSemantics\Platform\MySql\Statement\Name\CharsetName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4')));
        $collation = new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation(new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('utf8mb4_bin')));

        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict($latin1, $utf8), $options->charsetConflict($charset, $latin1, $utf8));
        self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch('utf8mb4_bin', 'latin1'), $options->charsetConflict($collation, $latin1, $utf8));
        self::assertNull($options->charsetConflict($collation, $utf8, $utf8));
    }

    public function testCollationNoticeRetainsTheResolvedNameAndWarningKind(): void
    {
        $option = new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation(new \SqlSemantics\Platform\MySql\Statement\Name\CollationName(new \SqlSemantics\Statement\Identifier\Name('UTF8_GENERAL_CI')));
        $rules = new DatabaseOptions();
        $notice = $rules->collationNotice($option, \SqlSemantics\Contract\GrammarRelease::MySql847);

        self::assertNotNull($notice);
        self::assertSame(3778, $notice->code());
        self::assertStringStartsWith("'utf8mb3_general_ci'", $notice->message());
        self::assertNull($rules->collationNotice($option, \SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertNull($rules->collationNotice($option, \SqlSemantics\Contract\GrammarRelease::MySql910));
    }

    public function testMismatchComparesTheNamedCollationWithTheNamedCharacterSet(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE latin1_bin');
            self::fail('The collation belongs to another character set.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertEquals(new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch('latin1_bin', 'utf8mb4'), $failure->problem);
        }
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_bin')->diagnostics());
    }

    public function testConflictFindsTheFirstConflictAmongTheOptions(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $sound = $semantics->analyze('CREATE DATABASE d CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase::class, $sound);
        self::assertNull((new DatabaseOptions())->conflict($sound->options));
        try {
            $semantics->analyze('CREATE DATABASE d CHARACTER SET utf8mb4 CHARACTER SET latin1');
            self::fail('Two character sets conflict.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\CharsetConflict::class, $failure->problem);
        }
    }
}
