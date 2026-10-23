<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin\Storage;

use MySqlMemory\Command\Admin\Storage\Autoextend;
use MySqlMemory\Error\Family\SchemaError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\ByteSize;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;

#[CoversClass(Autoextend::class)]
#[Small]
final class AutoextendTest extends TestCase
{
    /**
     * @return iterable<string, array{string, SchemaError|null}>
     */
    public static function providerSizes(): iterable
    {
        foreach (['0', '4194304', '4194305', '4210687', '4294967296'] as $size) {
            yield $size => [$size, null];
        }
        foreach (['1', '4194303', '4294967297'] as $size) {
            yield $size => [$size, SchemaError::AutoextendSize];
        }
        foreach (['4210688', '5242880'] as $size) {
            yield $size => [$size, SchemaError::AutoextendMultiple];
        }
    }

    #[DataProvider('providerSizes')]
    public function testProblemChecksBytesBeforeCompletePageMultiples(string $size, ?SchemaError $expected): void
    {
        $option = new SizeOption(SizeOptionKind::Autoextend, new ByteSize(new Numeral($size)));
        self::assertSame($expected, (new Autoextend())->problem([$option]));
    }

    public function testProblemUsesTheLastSetting(): void
    {
        $refused = new SizeOption(SizeOptionKind::Autoextend, new ByteSize(new Numeral('1')));
        $accepted = new SizeOption(SizeOptionKind::Autoextend, new ByteSize(new Numeral('4194304')));
        self::assertNull((new Autoextend())->problem([$refused, $accepted]));
        self::assertSame(SchemaError::AutoextendSize, (new Autoextend())->problem([$accepted, $refused]));
    }
}
