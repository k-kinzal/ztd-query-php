<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Command\Application;
use org\bovigo\vfs\vfsStream;
use Override;
use phpmock\Mock;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

#[CoversNothing]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CommandTest extends TestCase
{
    /**
     * @throws \phpmock\MockEnabledException
     */
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        vfsStream::setup('project', null, [
            'src' => ['Main.php' => '<?php throw new RuntimeException("Application files must not run"); function answer() { return 42; } function pending($id) { return $id; } function environment() { return getenv("APP_ENV"); }'],
            'ignored' => ['Invalid.php' => '<?php invalid syntax !'],
            'deliver.yaml' => "paths: [src]\ninclude: ['*.php']\nphpVersion: '8.3'\nformat: json\nenvironment: {APP_ENV: test}\n",
        ]);
        (new MockBuilder())->setNamespace('Deriver')->setName('is_dir')->setFunction(static fn (string $path): bool => is_dir('vfs:/' . $path))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable('vfs:/' . $path))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver')->setName('realpath')->setFunction(static fn (string $path): string => 'vfs:/' . $path)->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Deriver\Sources')->setName('str_contains')->setFunction(static fn (string $haystack, string $needle): bool => str_starts_with($haystack, 'vfs://') && $needle === '://' ? false : str_contains($haystack, $needle))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Configuration')->setName('is_file')->setFunction(static fn (string $path): bool => is_file('vfs:/' . $path))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Configuration')->setName('is_readable')->setFunction(static fn (string $path): bool => is_readable('vfs:/' . $path))->build()->enable();
        (new MockBuilder())->setNamespace('Deriver\Configuration')->setName('file_get_contents')->setFunction(static fn (string $path): string|false => file_get_contents('vfs:/' . $path))->build()->enable();
    }

    public function testCommandSelectsFilesAndDoesNotExecuteApplicationCode(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run(['query' => 'answer#return', '--path' => ['/project'], '--exclude' => ['ignored']], ['capture_stderr_separately' => true]));
        self::assertStringContainsString("[0] analyzed int\n42\n", $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testCommandLoadsConfigurationAndEnvironmentInputs(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run(['query' => 'environment#return', '--config' => '/project/deliver.yaml'], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('"result": "test"', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
    }

    public function testCommandOptionsOverrideConfiguredListsAndFormat(): void
    {
        file_put_contents('vfs://project/deliver.yaml', "paths: [ignored]\ninclude: ['Missing.php']\nexclude: ['Main.php']\nformat: json\n");
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run(['query' => 'answer#return', '--config' => '/project/deliver.yaml', '--path' => ['/project/src'], '--include' => ['Main.php'], '--exclude' => ['Other.php'], '--format' => 'text']));
        self::assertStringContainsString("[0] analyzed int\n42\n", $tester->getDisplay());
    }

    public function testCommandTreatsUnresolvedInputsAsCompletedPartialCandidates(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run(['query' => 'pending#return', '--config' => '/project/deliver.yaml']));
        self::assertStringContainsString('"type": "partials"', $tester->getDisplay());
        self::assertStringContainsString('"interrupted": false', $tester->getDisplay());
    }

    public function testCommandSeparatesParserFailuresFromReportOutput(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(1, $tester->run(['query' => 'answer#return', '--path' => ['/project/ignored'], '--format' => 'json'], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('Error:', $tester->getErrorOutput());
    }

    public function testCommandRejectsEmptyFileSelections(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(2, $tester->run(['query' => 'answer#return', '--path' => ['/project/src'], '--include' => ['Missing.php']], ['capture_stderr_separately' => true]));
        self::assertSame('', $tester->getDisplay());
        self::assertStringContainsString('No source files matched', $tester->getErrorOutput());
    }

    public function testCommandKeepsConsoleFormattingTagsLiteral(): void
    {
        file_put_contents('vfs://project/src/Main.php', '<?php function answer() { return "<error>literal</error>"; }');
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run(['query' => 'answer#return', '--path' => ['/project/src']], ['decorated' => true]));
        self::assertStringContainsString("'<error>literal</error>'", $tester->getDisplay());
    }

    public function testCommandAppliesTheTargetPhpVersionOverride(): void
    {
        file_put_contents('vfs://project/src/Main.php', '<?php class Item { public int $value { get => 42; } } function answer() { return (new Item())->value; }');
        $application = new Application();
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);
        self::assertSame(0, $tester->run(['query' => 'answer#return', '--config' => '/project/deliver.yaml', '--php-version' => '8.4'], ['capture_stderr_separately' => true]));
        self::assertStringContainsString('"result": 42', $tester->getDisplay());
        self::assertSame('', $tester->getErrorOutput());
    }

    #[Override]
    protected function tearDown(): void
    {
        Mock::disableAll();
        parent::tearDown();
    }
}
