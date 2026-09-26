<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Requirements\Config\Bootstrap;
use Requirements\Input\InvalidInputException;
use Tests\Fake\ProjectDirectory;

#[CoversClass(Bootstrap::class)]
#[Small]
final class BootstrapTest extends TestCase
{
    public function testLoadIncludesFile(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('bootstrap.php', "<?php\nfile_put_contents(__DIR__ . '/loaded.txt', 'loaded;', FILE_APPEND);\n");
        (new Bootstrap())->load($file);
        self::assertSame('loaded;', $project->read('loaded.txt'));
    }

    public function testLoadIncludesFileOnce(): void
    {
        $project = new ProjectDirectory();
        $file = $project->put('bootstrap.php', "<?php\nfile_put_contents(__DIR__ . '/loaded.txt', 'loaded;', FILE_APPEND);\n");
        $bootstrap = new Bootstrap();
        $bootstrap->load($file);
        $bootstrap->load($file);
        (new Bootstrap())->load($file);
        self::assertSame('loaded;', $project->read('loaded.txt'));
    }

    public function testLoadRejectsMissingFile(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Bootstrap does not exist: ' . $project->path('missing.php'));
        (new Bootstrap())->load($project->path('missing.php'));
    }

    public function testLoadRejectsDirectory(): void
    {
        $project = new ProjectDirectory();
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Bootstrap does not exist: ' . $project->directory);
        (new Bootstrap())->load($project->directory);
    }
}
