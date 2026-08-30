<?php

declare(strict_types=1);

namespace Tests\Unit\Driver;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\Driver\PdoParameterKind;

#[CoversClass(PdoParameterKind::class)]
final class PdoParameterKindTest extends TestCase
{
    public function testFromValueMapsPhpValuesToPdoParameterKinds(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        self::assertSame(PDO::PARAM_NULL, PdoParameterKind::fromValue(null));
        self::assertSame(PDO::PARAM_BOOL, PdoParameterKind::fromValue(true));
        self::assertSame(PDO::PARAM_INT, PdoParameterKind::fromValue(7));
        self::assertSame(PDO::PARAM_LOB, PdoParameterKind::fromValue($resource));
        self::assertSame(PDO::PARAM_STR, PdoParameterKind::fromValue('value'));
        self::assertSame(PDO::PARAM_STR, PdoParameterKind::fromValue(1.5));
        fclose($resource);
    }
}
