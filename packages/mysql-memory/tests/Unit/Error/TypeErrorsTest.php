<?php

declare(strict_types=1);

namespace Tests\Unit\Error;

use MySqlMemory\Error\TypeErrors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize;
use SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit;

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
}
