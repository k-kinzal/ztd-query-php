<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\TypeErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(TypeErrors::class)]
#[Small]
final class TypeErrorsTest extends TestCase
{
    public function testSizePreservesPrecisionAndNamesAcrossReleases(): void
    {
        $diagnostic = new InvalidTypeSize(TypeLimit::Precision, 'event_time', 7, 6);
        $errors = new TypeErrors();

        self::assertSame("Too big precision 7 specified for column 'event_time'. Maximum is 6.", $errors->size($diagnostic, true)->getMessage());
        self::assertSame("Too-big precision 7 specified for 'event_time'. Maximum is 6.", $errors->size($diagnostic)->getMessage());
        self::assertSame(1426, $errors->size($diagnostic, true)->getCode());
        self::assertSame(1439, $errors->size(new InvalidTypeSize(TypeLimit::Width, '', 0, 255))->getCode());
    }

    /**
     * @return iterable<string, array{'SRID'|'DEFAULT'|'ON UPDATE', int}>
     */
    public static function providerColumnAttributes(): iterable
    {
        yield 'spatial reference' => ['SRID', 1221];
        yield 'default' => ['DEFAULT', 1067];
        yield 'update timestamp' => ['ON UPDATE', 1294];
    }

    /**
     * @param 'SRID'|'DEFAULT'|'ON UPDATE' $attribute The incompatible declared attribute
     */
    #[DataProvider('providerColumnAttributes')]
    public function testColumnAttributeKeepsTheSpecificDefinitionError(string $attribute, int $code): void
    {
        $problem = new \SqlSemantics\Platform\MySql\Statement\Table\Problem\InvalidColumnAttribute(new Name('a'), $attribute);

        self::assertSame($code, (new TypeErrors())->columnAttribute($problem)->getCode());
    }


    public function testCharsetBoundsNamesByBytesAndRelease(): void
    {
        $errors = new TypeErrors();
        self::assertSame("Unknown character set: '" . str_repeat('a', 64) . "'", $errors->charset(str_repeat('a', 80))->getMessage());
        self::assertSame("Unknown character set: '" . str_repeat('a', 63) . "?'", $errors->charset(str_repeat('a', 63) . '界')->getMessage());
        self::assertSame("Unknown character set: '" . str_repeat('a', 63) . "'", $errors->charset(str_repeat('a', 63) . '界', true)->getMessage());
        self::assertSame("Unknown character set: 'bad'", $errors->charset("bad\0suffix")->getMessage());
        self::assertSame(1115, $errors->charset('bad')->getCode());
    }

}
