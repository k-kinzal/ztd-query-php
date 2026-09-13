<?php

declare(strict_types=1);

namespace Tests\Unit\Session;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Session\ParameterKind;

#[CoversClass(ParameterKind::class)]
final class ParameterKindTest extends TestCase
{
    public function testFromTypeMapsPhpValuesToPdoParameterTypes(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        self::assertSame(PDO::PARAM_NULL, ParameterKind::fromType(gettype(null)));
        self::assertSame(PDO::PARAM_BOOL, ParameterKind::fromType(gettype(true)));
        self::assertSame(PDO::PARAM_INT, ParameterKind::fromType(gettype(7)));
        self::assertSame(PDO::PARAM_LOB, ParameterKind::fromType(gettype($resource)));
        self::assertSame(PDO::PARAM_STR, ParameterKind::fromType(gettype('value')));
        self::assertSame(PDO::PARAM_STR, ParameterKind::fromType(gettype(1.5)));
        fclose($resource);
    }
}
