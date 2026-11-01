<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset;

#[CoversClass(ConvertCharset::class)]
#[Medium]
final class ConvertCharsetTest extends TestCase
{
    public function testDeriveCollationUsesTheDatabaseSessionEncodingForDefault(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_bin'), databaseCollation: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('latin1_bin'));
        $operation = $semantics->analyze('ALTER TABLE other.t CONVERT TO CHARSET DEFAULT COLLATE utf8mb4_bin', $semantics->context([], true, null, $settings));

        self::assertSame("COLLATION 'utf8mb4_bin' is not valid for CHARACTER SET 'latin1'", $operation->warnings()[0]->message());
    }

    public function testDeriveCommandAcceptsKnownCharacterSets(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT, b INT)');
        self::assertSame([], $semantics->analyze('ALTER TABLE t CONVERT TO CHARACTER SET utf8mb4', [$table])->diagnostics());
    }

    public function testDeriveCommandIsNeverReachedForACharacterSetTheReadingRefuses(): void
    {
        try {
            (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t CONVERT TO CHARSET absent COLLATE missing');
            self::fail('The character set does not exist.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCharset::class, $failure->problem);
            self::assertSame('absent', $failure->problem->name);
        }
    }

    public function testDeriveCollationRetainsIncompatibleEncodingMeaning(): void
    {
        $alter = (new Semantics(Dialect::MySql))->analyze('ALTER TABLE t CONVERT TO CHARSET utf8mb4 COLLATE latin1_bin');
        $warnings = $alter->warnings();

        self::assertCount(1, $warnings);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure::class, $warnings[0]);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\CollationMismatch::class, $warnings[0]->problem);
        self::assertSame("COLLATION 'latin1_bin' is not valid for CHARACTER SET 'utf8mb4'", $warnings[0]->message());
    }

    public function testRenderWritesTheCharsetAndTheCollation(): void
    {
        self::assertSame('ALTER TABLE t CONVERT TO CHAR SET DEFAULT COLLATE latin1_bin', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('ALTER TABLE t CONVERT TO CHAR SET DEFAULT COLLATE latin1_bin')->toString());
    }
}
