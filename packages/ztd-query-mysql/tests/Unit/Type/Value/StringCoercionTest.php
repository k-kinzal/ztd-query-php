<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SplFileInfo;
use ZtdQuery\Platform\MySql\Type\Value\StringCoercion;

#[CoversClass(StringCoercion::class)]
final class StringCoercionTest extends TestCase
{
    public function testStringValueAcceptsScalarsAndStringableObjects(): void
    {
        $coercion = new StringCoercion();
        self::assertSame('12', $coercion->stringValue(12));
        self::assertSame('1', $coercion->stringValue(true));
        self::assertSame('', $coercion->stringValue(false));
        self::assertSame('notes.sql', $coercion->stringValue(new SplFileInfo('notes.sql')));
    }

    public function testStringValueRejectsUnsupportedFixtureCells(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported value type for CTE shadowing.');
        (new StringCoercion())->stringValue(['nested']);
    }

    public function testReadStreamPreservesTheOriginalPosition(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        try {
            fwrite($stream, "a\0bc");
            fseek($stream, 2);
            $coercion = new StringCoercion();
            self::assertSame("a\0bc", $coercion->readStream($stream));
            self::assertSame(2, ftell($stream));
            self::assertSame("a\0bc", $coercion->stringValue($stream));
            self::assertSame(2, ftell($stream));
        } finally {
            fclose($stream);
        }
    }

}
