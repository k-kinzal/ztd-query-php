<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition;

use MySqlMemory\Command\Definition\InnoDbOptions;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(InnoDbOptions::class)]
#[Medium]
final class InnoDbOptionsTest extends TestCase
{
    public function testCheckReportsAllOptionWarningsBeforeTheEngineFailure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run("CREATE TABLE d.t(a INT) COMPRESSION='bad' ROW_FORMAT=FIXED");

        self::assertSame([['Warning', 1478, 'InnoDB: invalid ROW_FORMAT specifier.'], ['Warning', 1112, "InnoDB: Unsupported compression algorithm 'bad'"], ['Error', 1031, "Table storage engine for 't' doesn't have this option"]], $session->diagnostics->conditions);
    }

    public function testCheckAcceptsOptionsWhenInnoDbStrictModeIsDisabled(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET GLOBAL innodb_default_row_format='compact'; SET innodb_strict_mode=0; CREATE TABLE d.t(a INT) ROW_FORMAT=FIXED");

        self::assertSame([['Warning', 1478, 'InnoDB: assuming ROW_FORMAT=DYNAMIC.']], $session->diagnostics->conditions);
        self::assertNotNull($session->instance->dictionary->table('d', 't'));
    }

    public function testTableEncryptionAcceptsAnEmptyTemporarySettingInMySql57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query("CREATE DATABASE d; CREATE TEMPORARY TABLE d.t(a INT) ENCRYPTION=''");

        self::assertSame([], $session->diagnostics->conditions);
        self::assertCount(1, $session->query('SELECT * FROM d.t'));
    }

    public function testEncryptionReportsTheSpecificErrorBeforeTheEngineFailure(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->run("CREATE TABLE d.t(a INT) ENCRYPTION='bad'");

        self::assertSame([['Error', 3184, 'Invalid encryption option.'], ['Error', 1031, "Table storage engine for 't' doesn't have this option"]], $session->diagnostics->conditions);
    }

    public function testRequestedRetainsTheLastExplicitFormat(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(a INT) ROW_FORMAT=COMPACT ROW_FORMAT=REDUNDANT');
        $statement = $session->instance->dictionary->table('d', 't')?->definition->statement;
        self::assertNotNull($statement);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Redundant, (new InnoDbOptions())->requested($statement));
    }

    public function testFormatCapturesTheDefaultAtCreationAndKeepsItAcrossRename(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET GLOBAL innodb_default_row_format='COMPACT'; CREATE TABLE d.t(a INT)");
        $session->query("SET GLOBAL innodb_default_row_format='DYNAMIC'; RENAME TABLE d.t TO d.u");
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Compact, $session->instance->dictionary->table('d', 'u')?->definition->rowFormat);
    }

    public function testAntelopeUsesTheCurrentGlobalFormatAndRelease(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $options = new InnoDbOptions();
        self::assertTrue($options->antelope(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Dynamic, $session->variables));
        $session->query("SET GLOBAL innodb_file_format='Barracuda'");
        self::assertFalse($options->antelope(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Dynamic, $session->variables));
    }

    public function testRefusedFormatReportsAntelopeBeforeTheFallback(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->query('CREATE DATABASE d; SET innodb_strict_mode=0; CREATE TABLE d.t(a INT) ROW_FORMAT=DYNAMIC');
        self::assertSame([['Warning', 1478, 'InnoDB: ROW_FORMAT=DYNAMIC requires innodb_file_format > Antelope.'], ['Warning', 1478, 'InnoDB: assuming ROW_FORMAT=COMPACT.']], $session->diagnostics->conditions);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Compact, $session->instance->dictionary->table('d', 't')?->definition->rowFormat);
    }

    public function testRetainedDistinguishesMetadataChangesFromRebuilding(): void
    {
        $session = (new Instance())->connect();
        $session->query("CREATE DATABASE d; SET GLOBAL innodb_default_row_format='COMPACT'; CREATE TABLE d.t(a INT); SET GLOBAL innodb_default_row_format='DYNAMIC'; ALTER TABLE d.t COMMENT='changed'");
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Compact, $session->instance->dictionary->table('d', 't')?->definition->rowFormat);
        $session->query('ALTER TABLE d.t FORCE');
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::Dynamic, $session->instance->dictionary->table('d', 't')->definition->rowFormat);
    }

}
