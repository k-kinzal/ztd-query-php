<?php

declare(strict_types=1);

namespace Tests\Unit\ControlFlow;

use Deriver\ControlFlow\CallableIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(CallableIdentity::class)]
#[Small]
final class CallableIdentityTest extends TestCase
{
    /**
     * @param string $symbol Function, method, or synthetic identity
     * @param string $expected Lookup key
     */
    #[DataProvider('providerIdentities')]
    public function testKeyNormalizesOnlyNamedPhpCallables(string $symbol, string $expected): void
    {
        self::assertSame($expected, (new CallableIdentity())->key($symbol));
    }

    /**
     * @return array<string,array{string,string}>
     */
    public static function providerIdentities(): array
    {
        return [
            'function' => ['Target','target'],
            'qualified function' => ['\\N\\Target','n\\target'],
            'method' => ['\\N\\Box::RUN','n\\box::run'],
            'script' => ['script:Lib/A.php','script:Lib/A.php'],
            'closure' => ['closure:Lib/A.php:31:scope:Box','closure:Lib/A.php:31:scope:Box'],
            'parameter default' => ['N\\target:default:Value','N\\target:default:Value'],
            'method parameter default' => ['N\\Box::run:default:Value','N\\Box::run:default:Value'],
            'constant' => ['constant:N\\VALUE','constant:N\\VALUE'],
            'class constant' => ['constant:N\\Box::VALUE','constant:N\\Box::VALUE'],
            'property default' => ['N\\Box::$Value','N\\Box::$Value'],
            'native default' => ['native-default:Value','native-default:Value'],
            'trait initializer' => ['Box:trait-property:T::$Value','Box:trait-property:T::$Value'],
        ];
    }
}
