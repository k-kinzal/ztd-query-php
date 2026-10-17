<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin\Storage;

use MySqlMemory\Command\Admin\Storage\CreationOptions;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;

#[CoversClass(CreationOptions::class)]
#[Small]
final class CreationOptionsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function providerSizes(): iterable
    {
        foreach (['0', '1024', '2048', '4096', '8192', '16384'] as $size) {
            yield $size => [$size, null];
        }
        foreach (['1', '512'] as $size) {
            yield $size => [$size, 'InnoDB does not support FILE_BLOCK_SIZE=' . $size];
        }
        foreach (['32768', '65536'] as $size) {
            yield $size => [$size, 'InnoDB: Cannot create a tablespace with FILE_BLOCK_SIZE=' . $size . ' because INNODB_PAGE_SIZE=16384.'];
        }
    }

    #[DataProvider('providerSizes')]
    public function testBlockRetainsTheDefaultAndNativePageSizeDomain(string $size, ?string $expected): void
    {
        self::assertSame($expected, (new CreationOptions())->block([new SizeOption(SizeOptionKind::FileBlock, new ByteSize(new Numeral($size)))]));
    }

    public function testCheckReportsSizeBeforeLegacyFilenameConditions(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->run("CREATE TABLESPACE s ADD DATAFILE 'text' FILE_BLOCK_SIZE 1");

        self::assertSame([['Error', 1478, 'InnoDB does not support FILE_BLOCK_SIZE=1'], ['Error', 3121, "Incorrect File Name 'text'."], ['Error', 1528, 'Failed to create TABLESPACE s'], ['Error', 1031, "Table storage engine for 's' doesn't have this option"]], $session->diagnostics->conditions);
    }

    public function testLegacyKeepsTheAdditionalExtensionCondition(): void
    {
        $error = SchemaError::WrongFileName->error('longname');
        $converted = (new CreationOptions())->legacy($error, 'longname', 's');
        self::assertSame([[3121, 'An IBD filepath must end with `.ibd`.'], [1528, 'Failed to create TABLESPACE s'], [1031, "Table storage engine for 's' doesn't have this option"]], $converted->following);
        self::assertSame($error, $converted->getPrevious());
        $other = SchemaError::TablespaceExists->error('s');
        self::assertSame($other, (new CreationOptions())->legacy($other, 'longname', 's'));
    }
}
