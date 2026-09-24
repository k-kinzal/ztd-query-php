<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility\Transfer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binding\Statement\Utility\Transfer\CopySettings;
use SqlSemantics\Model\Statement\Loading\Copy;

#[CoversClass(CopySettings::class)]
#[Medium]
final class CopySettingsTest extends TestCase
{
    public function testOptionsBuildTypedOptions(): void
    {
        $node = new \SqlParser\Parser\Node('copy_options', 0, []);
        $options = CopySettings::options([['format', 'csv', $node], ['header', 'match', $node], ['on_error', 'IGNORE', $node], ['force_null', ['a'], $node]], $node);
        self::assertSame(Copy\CopyFormat::Csv, $options->format);
        self::assertSame(Copy\CopyHeader::Match, $options->header);
        self::assertSame(Copy\CopyErrorAction::Ignore, $options->onError);
        self::assertInstanceOf(Copy\ListedColumns::class, $options->forceNull);
    }

    /**
     * @param string|int|list<string>|null $argument
     */
    #[TestWith(['freeze', 'off', false])]
    #[TestWith(['freeze', null, true])]
    #[TestWith(['delimiter', 5, '5'])]
    #[TestWith(['encoding', ['a', 'b'], 'a.b'])]
    #[TestWith(['format', 'CSV', null])]
    #[TestWith(['unknown', 'x', null])]
    public function testValueConvertsArguments(string $name, string|int|array|null $argument, string|bool|null $expected): void
    {
        self::assertSame($expected, CopySettings::value($name, $argument));
    }

    #[TestWith([null, Copy\CopyHeader::Present])]
    #[TestWith(['MATCH', Copy\CopyHeader::Match])]
    #[TestWith([0, Copy\CopyHeader::Absent])]
    #[TestWith(['yes', null])]
    public function testHeaderReadsBooleanOrMatch(string|int|null $argument, ?Copy\CopyHeader $expected): void
    {
        self::assertSame($expected, CopySettings::header($argument));
    }

    public function testTypedReturnsOnlyTheRequestedClass(): void
    {
        self::assertSame(Copy\CopyFormat::Csv, CopySettings::typed(['format' => Copy\CopyFormat::Csv], 'format', Copy\CopyFormat::class));
        self::assertNull(CopySettings::typed(['format' => 'csv'], 'format', Copy\CopyFormat::class));
    }

    public function testTextReturnsOnlyStrings(): void
    {
        self::assertSame('|', CopySettings::text(['delimiter' => '|'], 'delimiter'));
        self::assertNull(CopySettings::text(['freeze' => true], 'freeze'));
    }

    public function testChoiceReturnsOnlyColumnChoices(): void
    {
        self::assertInstanceOf(Copy\EveryColumn::class, CopySettings::choice(['force_quote' => new Copy\EveryColumn()], 'force_quote'));
        self::assertNull(CopySettings::choice([], 'force_quote'));
    }

    public function testStringReadsTextNumbersAndNameLists(): void
    {
        self::assertSame('7', CopySettings::string(7));
        self::assertSame('a.b', CopySettings::string(['a', 'b']));
        self::assertNull(CopySettings::string(true));
    }

    public function testColumnsReadsAnAsteriskOrNames(): void
    {
        self::assertInstanceOf(Copy\EveryColumn::class, CopySettings::columns(true));
        self::assertInstanceOf(Copy\ListedColumns::class, CopySettings::columns(['a']));
        self::assertNull(CopySettings::columns('a'));
    }

    /**
     * @param string|int|list<string>|true|null $argument
     */
    #[TestWith(['freeze', 1, true])]
    #[TestWith(['freeze', 'on', true])]
    #[TestWith(['freeze', ['a'], null])]
    #[TestWith(['format', 5, null])]
    #[TestWith(['null', 'x', 'x'])]
    #[TestWith(['default', 'd', 'd'])]
    #[TestWith(['quote', 'q', 'q'])]
    #[TestWith(['escape', 'e', 'e'])]
    #[TestWith(['delimiter', ';', ';'])]
    public function testValueReadsEachScalarOption(string $name, string|int|array|bool|null $argument, string|bool|null $expected): void
    {
        self::assertSame($expected, CopySettings::value($name, $argument));
    }

    public function testValueReadsEachColumnChoiceOption(): void
    {
        self::assertInstanceOf(Copy\EveryColumn::class, CopySettings::value('force_quote', true));
        self::assertInstanceOf(Copy\ListedColumns::class, CopySettings::value('force_not_null', ['a']));
        self::assertInstanceOf(Copy\EveryColumn::class, CopySettings::value('force_null', true));
    }

    public function testValueReadsTheLogVerbosityInAnyCase(): void
    {
        self::assertSame(Copy\CopyLogVerbosity::Verbose, CopySettings::value('log_verbosity', 'VERBOSE'));
        self::assertNull(CopySettings::value('log_verbosity', ['verbose']));
    }

    public function testHeaderReadsAnIntegerFlagAndRejectsAList(): void
    {
        self::assertSame(Copy\CopyHeader::Present, CopySettings::header(1));
        self::assertNull(CopySettings::header(['a']));
    }

    public function testOptionsApplyTheServerDefaults(): void
    {
        $node = new \SqlParser\Parser\Node('copy_options', 0, []);
        $options = CopySettings::options([], $node);
        self::assertFalse($options->freeze);
        self::assertSame(Copy\CopyLogVerbosity::Default, $options->logVerbosity);
        self::assertSame(Copy\CopyFormat::Text, $options->format);
        self::assertSame(Copy\CopyHeader::Absent, $options->header);
    }

    public function testOptionsReadAWrittenFreezeAndVerbosity(): void
    {
        $node = new \SqlParser\Parser\Node('copy_options', 0, []);
        $options = CopySettings::options([['freeze', null, $node], ['log_verbosity', 'verbose', $node]], $node);
        self::assertTrue($options->freeze);
        self::assertSame(Copy\CopyLogVerbosity::Verbose, $options->logVerbosity);
    }

    public function testOptionsRejectAnUnknownOption(): void
    {
        $node = new \SqlParser\Parser\Node('copy_options', 0, []);
        $this->expectException(\SqlSemantics\InvalidSql::class);
        CopySettings::options([['unknown', 'x', $node]], $node);
    }
}
