<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver;
use Deriver\Deriver\Query;
use Deriver\Deriver\Sources\Path;
use Deriver\Exception\InvalidInputException;
use org\bovigo\vfs\vfsStream;
use Override;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SelectorQueryTest extends TestCase
{
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testPreparedRelativeSourcesAndSelectorsKeepTheirOriginalDirectory(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project', null, ['src' => []]);
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'src/a.php';
        $contents = "<?php\nunknown('captured');\n";
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $other = vfsStream::newDirectory('other/src');
        $other->at($source);
        $name1 = 'src/a.php';
        $contents1 = "<?php\nunknown('different');\n";
        $url1 = $other->url() . '/' . $name1;
        file_put_contents($url1, $contents1);
        $cwd = $workingDirectory;
        self::assertNotFalse($cwd);
        try {
            $workingDirectory = '/' . $source->path();
            $deriver = Deriver::prepare(['src/a.php']);
            $path1 = $path;
            $path1 = str_starts_with($path1, '/') ? $path1 : $workingDirectory . '/' . $path1;
            self::assertTrue(unlink('vfs:/' . Path::normalize($path1)));
            $workingDirectory = '/' . $other->path();
            self::assertSame('captured', $deriver->derive('src/a.php:2:1#argument(0)')[0]->result);
            self::assertSame('captured', $deriver->derive($path . ':2:1#argument(0)')[0]->result);
        } finally {
            $workingDirectory = $cwd;
        }
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveSelectsNamespacedReturnsAndFormalParameterOrigins(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'app.php';
        $contents = '<?php namespace App; class Repository { public static function find($id){return $id * 2;} } Repository::find(3); Repository::find(5);';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $deriver = Deriver::prepare([$path]);
        $parameters = $deriver->derive('App\Repository::find#parameter(id)');
        $returns = $deriver->derive('App\Repository::find#return');
        self::assertSame([3, 5], array_column(iterator_to_array($parameters, false), 'result'));
        self::assertSame([6, 10], array_column(iterator_to_array($returns, false), 'result'));
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveSelectsArgumentExpressionsWithoutExecutingTheCall(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'source #1.php';
        $contents = "<?php\n\$x = 3;\nunknown(\$x++, \$x);\n";
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $files = [$path];
        $deriver = Deriver::prepare($files);
        self::assertSame(3, $deriver->derive($path . ':3:1#argument(0)')[0]->result);
        self::assertSame(4, $deriver->derive($path . ':3:1#argument(1)')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveResolvesRelativePathsAgainstTheWorkingDirectory(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project', null, ['src' => []]);
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'src/a.php';
        $contents = "<?php\nunknown(sql: 'SELECT 1');\n";
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $cwd = $workingDirectory;
        self::assertNotFalse($cwd);
        $workingDirectory = '/' . $source->path();
        try {
            $query = Query::parse('src/./a.php:2:1#argument(sql)');
            self::assertSame('SELECT 1', Deriver::prepare([$path])->derive($query->toString())[0]->result);
            self::assertSame('SELECT 1', Deriver::prepare(['src/a.php'])->derive($path . ':2:1#argument(0)')[0]->result);
        } finally {
            $workingDirectory = $cwd;
        }
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveFindsArgumentsInsideClosures(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'a.php';
        $contents = "<?php\n\$run = function () {\n    unknown('inside');\n};\n";
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        self::assertSame('inside', Deriver::prepare([$path])->derive($path . ':3:5#argument(0)')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveUsesByteColumnsWithUtf8AndCrLfSource(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $prefix = '$label = "日本語"; ';
        $name = 'a.php';
        $contents = "<?php\r\n" . $prefix . "unknown('sql');\r\n";
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $query = $path . ':2:' . (strlen($prefix) + 1) . '#argument(0)';
        self::assertSame('sql', Deriver::prepare([$path])->derive($query)[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveRejectsCallsSharingTheSameStartPosition(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'a.php';
        $contents = "<?php\nfactory()->send('sql');\n";
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('exactly one captured call');
        Deriver::prepare([$path])->derive($path . ':2:1#argument(0)');
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveDoesNotAddAFileNamedOnlyByTheSelector(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'selected.php';
        $contents = '<?php unknown(1);';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $selected = '/' . $source->path() . '/' . $name;
        $name1 = 'excluded.php';
        $contents1 = '<?php unknown(2);';
        $url1 = $source->url() . '/' . $name1;
        file_put_contents($url1, $contents1);
        $excluded = '/' . $source->path() . '/' . $name1;
        $this->expectException(InvalidInputException::class);
        Deriver::prepare([$selected])->derive($excluded . ':1:7#argument(0)');
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveRejectsAnUnknownFormalParameter(): void
    {
        $workingDirectory = '/project';
        $filesystem = vfsStream::setup('project');
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_file')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_file('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('is_readable')->setFunction(static function (string $path) use (&$workingDirectory): bool {
            return is_readable('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('file_get_contents')->setFunction(static function (string $path) use (&$workingDirectory): string|false {
            return file_get_contents('vfs:/' . Path::normalize(str_starts_with($path, '/') ? $path : $workingDirectory . '/' . $path));
        })->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Selection')->setName('getcwd')->setFunction(static function () use (&$workingDirectory): string {
            return $workingDirectory;
        })->build()->enable();
        $source = $filesystem;
        $name = 'a.php';
        $contents = '<?php function find($id){return $id;}';
        $url = $source->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $source->path() . '/' . $name;
        $this->expectException(InvalidInputException::class);
        Deriver::prepare([$path])->derive('find#parameter(missing)');
    }
    #[Override]
    protected function tearDown(): void
    {
        Mock::disableAll();
        parent::tearDown();
    }
}
