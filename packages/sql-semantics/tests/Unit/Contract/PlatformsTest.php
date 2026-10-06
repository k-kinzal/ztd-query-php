<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Platform\Sqlite\Platform;

#[CoversClass(Platforms::class)]
#[Small]
final class PlatformsTest extends TestCase
{
    public function testOfAnswersTheInstalledPlatformOnce(): void
    {
        $platform = Platforms::of('sqlite');

        self::assertInstanceOf(Platform::class, $platform);
        self::assertSame($platform, Platforms::of('sqlite'));
    }

    public function testOfRefusesAnUnknownDatabaseFamily(): void
    {
        $this->expectExceptionMessage('The database package for oracle is not installed.');

        Platforms::of('oracle');
    }

    public function testPlatformClassNamesTheFixedPackageClass(): void
    {
        self::assertSame(Platform::class, Platforms::platformClass('sqlite'));
        self::assertSame('SqlSemantics\\Platform\\MySql\\Platform', Platforms::platformClass('mysql'));
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Platform', Platforms::platformClass('postgresql'));
        self::assertNull(Platforms::platformClass('oracle'));
    }
}
