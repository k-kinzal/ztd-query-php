<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Function\Text\Compression;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Compression::class)]
#[Small]
final class CompressionTest extends TestCase
{
    public function testRoutinesNamesTheCompressionFunctionsAndLoadFile(): void
    {
        self::assertSame(['COMPRESS', 'UNCOMPRESS', 'UNCOMPRESSED_LENGTH', 'LOAD_FILE'], array_map(static fn ($routine): string => $routine->name, (new Compression())->routines()));
    }

    public function testBytesReadsTheTextOfTheArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(COMPRESS(123)), COMPRESS(NULL), LOAD_FILE('/etc/passwd')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['03000000789C3334320600012D0097', null, null]], $result->rows);
    }

    public function testCompressWritesTheLengthAndTheStream(): void
    {
        self::assertSame(['01000000789c4b040000620062', '', '02000000789c4b54000000e40082'], [bin2hex((new Compression())->compress('a')), (new Compression())->compress(''), bin2hex((new Compression())->compress('a '))]);
    }

    public function testLengthReadsThe30LowBits(): void
    {
        self::assertSame([0, 610493025, 3], [(new Compression())->length('abcd'), (new Compression())->length('abcdef'), (new Compression())->length((string) hex2bin('03000080789c'))]);
    }

    public function testUncompressWarnsOfABadStream(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT UNCOMPRESS(COMPRESS('abc')), UNCOMPRESS(''), UNCOMPRESS('abc'), UNCOMPRESS(x'02000000789c4b4c4a0600024d0127'), UNCOMPRESS(x'FFFFFF3F789c4b0400'), UNCOMPRESS(x'10000000789c4b4c4a0600024d0127FFFF')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['abc', '', null, null, null, 'abc']], $result->rows);
        self::assertSame([
            ['Warning', '1259', 'ZLIB: Input data corrupted'],
            ['Warning', '1258', 'ZLIB: Not enough room in the output buffer (probably, length of uncompressed data was corrupted)'],
            ['Warning', '1256', 'Uncompressed data size too large; the maximum size is 67108864 (probably, length of uncompressed data was corrupted)'],
        ], $warnings->rows);
    }
}
