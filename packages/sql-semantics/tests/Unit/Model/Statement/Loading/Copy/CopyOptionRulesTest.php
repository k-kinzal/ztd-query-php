<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\CopyErrorAction;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFormat;
use SqlSemantics\Model\Statement\Loading\Copy\CopyHeader;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptionRules;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptions;
use SqlSemantics\Model\Statement\Loading\Copy\EveryColumn;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(CopyOptionRules::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CopyOptionRulesTest extends TestCase
{
    public function testFormatRejectsTextOptionsInBinaryFormat(): void
    {
        $this->expectException(InvalidStructure::class);
        new CopyOptions(CopyFormat::Binary, header: CopyHeader::Present);
    }

    public function testFormatRejectsAnEmptyEncoding(): void
    {
        $this->expectException(InvalidStructure::class);
        new CopyOptions(encoding: '');
    }

    public function testCharactersRejectALetterDelimiterInText(): void
    {
        $this->expectException(InvalidStructure::class);
        new CopyOptions(delimiter: 'a');
    }

    public function testCharactersRejectADelimiterInsideTheNullSpelling(): void
    {
        $this->expectException(InvalidStructure::class);
        new CopyOptions(CopyFormat::Csv, delimiter: ';', null: 'a;b');
    }

    public function testCharactersRejectEqualNullAndDefaultSpellings(): void
    {
        $this->expectException(InvalidStructure::class);
        new CopyOptions(null: 'x', default: 'x');
    }

    public function testCharactersRejectTheQuoteAsDelimiter(): void
    {
        $this->expectException(InvalidStructure::class);
        new CopyOptions(CopyFormat::Csv, delimiter: '"');
    }

    public function testReadingRejectsForceQuote(): void
    {
        $this->expectException(InvalidStructure::class);
        CopyOptionRules::reading(new CopyOptions(CopyFormat::Csv, forceQuote: new EveryColumn()));
    }

    public function testWritingRejectsReadOnlyOptions(): void
    {
        CopyOptionRules::writing(new CopyOptions(CopyFormat::Csv, forceQuote: new EveryColumn()));
        $this->expectException(InvalidStructure::class);
        CopyOptionRules::writing(new CopyOptions(onError: CopyErrorAction::Stop));
    }

    public function testSpellingsRejectALineBreakInTheNullSpelling(): void
    {
        CopyOptionRules::spellings(new CopyOptions(), "\t", 'n', '"');
        $this->expectException(InvalidStructure::class);
        CopyOptionRules::spellings(new CopyOptions(), "\t", "a\nb", '"');
    }

    /**
     * @return array<string, array{Closure(): CopyOptions}>
     */
    public static function providerFormatRejectsOptionsTheFormatDoesNotAccept(): array
    {
        return [
            'binary delimiter' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Binary, delimiter: ',')],
            'binary null' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Binary, null: 'x')],
            'binary default' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Binary, default: 'x')],
            'binary ignore' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Binary, onError: CopyErrorAction::Ignore)],
            'text quote' => [static fn (): CopyOptions => new CopyOptions(quote: "'")],
            'text escape' => [static fn (): CopyOptions => new CopyOptions(escape: "'")],
            'text force quote' => [static fn (): CopyOptions => new CopyOptions(forceQuote: new EveryColumn())],
            'text force not null' => [static fn (): CopyOptions => new CopyOptions(forceNotNull: new EveryColumn())],
            'text force null' => [static fn (): CopyOptions => new CopyOptions(forceNull: new EveryColumn())],
            'csv newline delimiter' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Csv, delimiter: "\n")],
            'csv long quote' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Csv, quote: 'ab')],
            'csv long escape' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Csv, escape: 'ab')],
            'default with line break' => [static fn (): CopyOptions => new CopyOptions(default: "a\nb")],
            'default with delimiter' => [static fn (): CopyOptions => new CopyOptions(default: "a\tb")],
            'csv null with quote' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Csv, null: 'a"b')],
            'csv default with quote' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Csv, default: 'a"b')],
        ];
    }

    /**
     * @param Closure(): CopyOptions $options
     */
    #[DataProvider('providerFormatRejectsOptionsTheFormatDoesNotAccept')]
    public function testFormatRejectsOptionsTheFormatDoesNotAccept(Closure $options): void
    {
        $this->expectException(InvalidStructure::class);
        $options();
    }

    public function testCharactersAcceptEveryFormatWithItsOwnOptions(): void
    {
        self::assertSame("\t", (new CopyOptions(CopyFormat::Binary, onError: CopyErrorAction::Stop))->effectiveDelimiter());
        self::assertSame(',', (new CopyOptions(CopyFormat::Csv, quote: "'", escape: '\\', forceNotNull: new EveryColumn()))->effectiveDelimiter());
        self::assertSame('"', (new CopyOptions(CopyFormat::Csv, delimiter: '"', quote: "'"))->effectiveDelimiter());
        self::assertSame('y', (new CopyOptions(null: 'y', default: 'x'))->effectiveNull());
    }

    /**
     * @return array<string, array{Closure(): CopyOptions}>
     */
    public static function providerWritingRejectsEachReadOnlyOption(): array
    {
        return [
            'force not null' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Csv, forceNotNull: new EveryColumn())],
            'force null' => [static fn (): CopyOptions => new CopyOptions(format: CopyFormat::Csv, forceNull: new EveryColumn())],
            'freeze' => [static fn (): CopyOptions => new CopyOptions(freeze: true)],
            'default' => [static fn (): CopyOptions => new CopyOptions(default: 'x')],
            'header match' => [static fn (): CopyOptions => new CopyOptions(header: CopyHeader::Match)],
        ];
    }

    /**
     * @param Closure(): CopyOptions $options
     */
    #[DataProvider('providerWritingRejectsEachReadOnlyOption')]
    public function testWritingRejectsEachReadOnlyOption(Closure $options): void
    {
        $built = $options();
        $this->expectException(InvalidStructure::class);
        CopyOptionRules::writing($built);
    }
}
