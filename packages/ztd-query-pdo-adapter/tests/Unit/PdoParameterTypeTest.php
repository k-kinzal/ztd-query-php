<?php

declare(strict_types=1);

namespace Tests\Unit;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PdoParameterType;

#[CoversClass(PdoParameterType::class)]
final class PdoParameterTypeTest extends TestCase
{
    public function testMapsPhpValuesToPdoParameterTypes(): void
    {
        $resource = fopen('php://memory', 'r');
        self::assertIsResource($resource);

        self::assertSame(PDO::PARAM_NULL, PdoParameterType::fromValue(null));
        self::assertSame(PDO::PARAM_BOOL, PdoParameterType::fromValue(true));
        self::assertSame(PDO::PARAM_INT, PdoParameterType::fromValue(7));
        self::assertSame(PDO::PARAM_LOB, PdoParameterType::fromValue($resource));
        self::assertSame(PDO::PARAM_STR, PdoParameterType::fromValue('value'));
        self::assertSame(PDO::PARAM_STR, PdoParameterType::fromValue(1.5));
        fclose($resource);
    }
}
