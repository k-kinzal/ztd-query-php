<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\CopyErrorAction;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFormat;
use SqlSemantics\Model\Statement\Loading\Copy\CopyHeader;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptionRules;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptions;
use SqlSemantics\Model\Statement\Loading\Copy\EveryColumn;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(CopyOptionRules::class)]
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
}
