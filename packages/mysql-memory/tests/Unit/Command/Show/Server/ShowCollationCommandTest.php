<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Server\ShowCollationCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(ShowCollationCommand::class)]
#[Small]
final class ShowCollationCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowCollationCommand())->clearsDiagnostics());
    }

    public function testExecuteListsCollationsAndCharacterSets(): void
    {
        $session = (new Instance())->connect();

        $collations = $session->query("SHOW COLLATION LIKE 'utf8mb4_0900_a%'")[0];
        $charsets = $session->query("SHOW CHARSET LIKE 'UTF8%'")[0];

        self::assertInstanceOf(ResultSet::class, $collations);
        self::assertInstanceOf(ResultSet::class, $charsets);
        self::assertSame([['utf8mb4_0900_ai_ci', 'utf8mb4', '255', 'Yes', 'Yes', '0', 'NO PAD'], ['utf8mb4_0900_as_ci', 'utf8mb4', '305', '', 'Yes', '0', 'NO PAD'], ['utf8mb4_0900_as_cs', 'utf8mb4', '278', '', 'Yes', '0', 'NO PAD']], $collations->rows);
        self::assertSame([['utf8mb3', 'UTF-8 Unicode', 'utf8mb3_general_ci', '3'], ['utf8mb4', 'UTF-8 Unicode', 'utf8mb4_0900_ai_ci', '4']], $charsets->rows);
    }

    public function testExecutePreservesLegacyUtf8NamesAndMetadata(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $collations = $session->query("SHOW COLLATION LIKE 'utf8_general_ci'")[0];
        $charsets = $session->query("SHOW CHARSET LIKE 'utf8'")[0];

        self::assertInstanceOf(ResultSet::class, $collations);
        self::assertInstanceOf(ResultSet::class, $charsets);
        self::assertSame([['utf8_general_ci', 'utf8', '33', 'Yes', 'Yes', '1']], $collations->rows);
        self::assertSame([['utf8', 'UTF-8 Unicode', 'utf8_general_ci', '3']], $charsets->rows);
        self::assertCount(6, $collations->columns);
        self::assertSame(3, $charsets->columns[3]->length);
    }

    public function testCollationsComeInTheOrderOfGeneralCi(): void
    {
        $names = array_column((new ShowCollationCommand())->collations(GrammarRelease::MySql847), 0);

        self::assertCount(286, $names);
        self::assertSame(['ucs2_romanian_ci', 'ucs2_roman_ci'], array_values(array_intersect($names, ['ucs2_roman_ci', 'ucs2_romanian_ci'])));
    }

    public function testCharsetsListsTheCharacterSetsOfTheRelease(): void
    {
        $charsets = (new ShowCollationCommand())->charsets(GrammarRelease::MySql847);

        self::assertCount(41, $charsets);
        self::assertSame(['binary', 'Binary pseudo charset', 'binary', 1], $charsets[3]);
    }

    public function testCollationHeadingsNameTheColumns(): void
    {
        self::assertSame(['Collation', 'Charset', 'Id', 'Default', 'Compiled', 'Sortlen', 'Pad_attribute'], array_map(static fn (Heading $heading): string => $heading->name, (new ShowCollationCommand())->collationHeadings()));
    }

    public function testCharsetHeadingsNameTheColumns(): void
    {
        self::assertSame(['Charset', 'Description', 'Default collation', 'Maxlen'], array_map(static fn (Heading $heading): string => $heading->name, (new ShowCollationCommand())->charsetHeadings()));
    }
}
