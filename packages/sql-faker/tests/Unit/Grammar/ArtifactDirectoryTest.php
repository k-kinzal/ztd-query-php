<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\ArtifactDirectory;

#[CoversClass(ArtifactDirectory::class)]
final class ArtifactDirectoryTest extends TestCase
{
    public function testPreparedNamesTheDirectoryOfAnArtifactThatAlreadyHasOne(): void
    {
        $directory = sys_get_temp_dir();

        self::assertSame($directory, (new ArtifactDirectory())->prepared($directory . '/lexical.php'));
    }

    public function testPreparedCreatesADirectoryThatDoesNotExistYet(): void
    {
        $directory = sys_get_temp_dir() . '/sql-faker-artifact-' . getmypid() . '/nested';

        try {
            self::assertSame($directory, (new ArtifactDirectory())->prepared($directory . '/lexical.php'));
            self::assertDirectoryExists($directory);
        } finally {
            if (is_dir($directory)) {
                rmdir($directory);
                rmdir(dirname($directory));
            }
        }
    }

    public function testPreparedReportsTheAncestorThatCanNeverHoldADirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/dev/null is not a writable directory');

        (new ArtifactDirectory())->prepared('/dev/null/no-such/lexical.php');
    }
}
