<?php

declare (strict_types=1);

namespace Tests\Integration;

use Deriver\Deriver;
use Deriver\Deriver\Candidate\CandidateStatus;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Sources\Path;
use org\bovigo\vfs\vfsStream;
use Override;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class EnvironmentTest extends TestCase
{
    /**
     * @param array<string, scalar|array<array-key, mixed>|null> $environment Explicit external inputs
     * @param scalar|array<array-key, mixed>|null $expected Selected return value
     * @throws \phpmock\MockEnabledException
     */
    #[DataProvider('providerInputs')]
    public function testDeriveUsesVariableAndEnvironmentNames(string $code, array $environment, mixed $expected): void
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
        $sources = $filesystem;
        $name = 'app.php';
        $contents = '<?php ' . $code;
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $result = Deriver::prepare([$path], new DeriverConfig(environment: $environment))->derive('target#return');
        self::assertSame([$expected], array_column(iterator_to_array($result, false), 'result'));
    }
    /**
     * @return iterable<string, array{string, array<string, scalar|array<array-key, mixed>|null>, scalar|array<array-key, mixed>|null}>
     */
    public static function providerInputs(): iterable
    {
        yield 'superglobal' => ['function target(){return $_GET["id"];}', ['$_GET' => ['id' => 123]], 123];
        yield 'global' => ['function target(){global $prefix;return $prefix."users";}', ['$prefix' => 'app_'], 'app_users'];
        yield 'named environment' => ['function target(){return getenv("APP_ENV");}', ['APP_ENV' => 'test'], 'test'];
        yield 'explicit absent environment' => ['function target(){return getenv("ABSENT");}', ['ABSENT' => false], false];
        yield 'constant from source' => ['const APP_ENV="source";function target(){return APP_ENV;}', ['APP_ENV' => 'environment'], 'source'];
        yield 'static from source' => ['function nextValue(){static $n=4;return ++$n;}function target(){return nextValue();}', ['n' => 99, '$n' => 99], 5];
        yield 'local from source' => ['function target(){$name="source";return $name;}', ['$name' => 'environment'], 'source'];
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    public function testDeriveIdentifiesEnvironmentContentsAndSourceChanges(): void
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
        $sources = $filesystem;
        $name = 'app.php';
        $contents = '<?php function target(){return getenv("APP_ENV");}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $configuration = new DeriverConfig(environment: ['APP_ENV' => 'test']);
        $deriver = Deriver::prepare([$path], $configuration);
        self::assertSame('test', $deriver->derive('target#return')[0]->result);
        self::assertSame('production', Deriver::prepare([$path], new DeriverConfig(environment: ['APP_ENV' => 'production']))->derive('target#return')[0]->result);
        $name1 = 'app.php';
        $contents1 = '<?php function target(){return "changed";}';
        $url1 = $sources->url() . '/' . $name1;
        file_put_contents($url1, $contents1);
        self::assertSame('changed', Deriver::prepare([$path], $configuration)->derive('target#return')[0]->result);
    }
    /**
     * @throws \phpmock\MockEnabledException
     */
    #[DataProvider('providerUnresolvedInputs')]
    public function testDeriveKeepsUnspecifiedInputsAndEventsUnresolved(string $expression): void
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
        $sources = $filesystem;
        $name = 'app.php';
        $contents = '<?php function target(){return ' . $expression . ';}';
        $url = $sources->url() . '/' . $name;
        file_put_contents($url, $contents);
        $path = '/' . $sources->path() . '/' . $name;
        $result = Deriver::prepare([$path], new DeriverConfig(environment: ['APP_ENV' => 'test', 'time' => 123, 'microtime' => 'captured']))->derive('target#return');
        self::assertCount(1, $result);
        self::assertSame(CandidateStatus::Partials, $result[0]->type);
    }
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerUnresolvedInputs(): iterable
    {
        yield 'missing name' => ['getenv("MISSING")'];
        yield 'whole environment' => ['getenv()'];
        yield 'clock event' => ['time()'];
        yield 'microtime event' => ['microtime()'];
    }
    #[Override]
    protected function tearDown(): void
    {
        Mock::disableAll();
        parent::tearDown();
    }
}
