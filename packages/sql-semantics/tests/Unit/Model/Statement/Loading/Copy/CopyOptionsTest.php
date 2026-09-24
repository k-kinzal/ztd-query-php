<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\CopyFormat;
use SqlSemantics\Model\Statement\Loading\Copy\CopyOptions;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(CopyOptions::class)]
final class CopyOptionsTest extends TestCase
{
    public function testEffectiveDelimiterFollowsTheFormat(): void
    {
        self::assertSame("\t", (new CopyOptions())->effectiveDelimiter());
        self::assertSame(',', (new CopyOptions(CopyFormat::Csv))->effectiveDelimiter());
        self::assertSame('|', (new CopyOptions(delimiter: '|'))->effectiveDelimiter());
    }

    public function testEffectiveNullFollowsTheFormat(): void
    {
        self::assertSame('\\N', (new CopyOptions())->effectiveNull());
        self::assertSame('', (new CopyOptions(CopyFormat::Csv))->effectiveNull());
    }

    public function testRejectsAQuoteOutsideCsv(): void
    {
        $this->expectException(InvalidStructure::class);
        new CopyOptions(quote: "'");
    }
}
