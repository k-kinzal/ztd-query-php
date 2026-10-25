<?php

declare(strict_types=1);

namespace Tests\Integration\Rules;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[CoversNothing]
#[Large]
final class ConfiguredUnusedClassDetectionTest extends TestCase
{
    /**
     * @param list<string> $classes
     * @param list<string> $expected
     * @throws JsonException When test configuration cannot be encoded
     */
    #[DataProvider('providerClassReferences')]
    public function testReportsUnreferencedClassesSelectedInNeon(string $source, array $classes, array $expected): void
    {
        $directory = sys_get_temp_dir() . '/unused-class-' . bin2hex(random_bytes(8));
        $package = dirname(__DIR__, 3);
        $fixture = $directory . '/source/Example.php';
        $filesystem = new Filesystem();
        $extension = json_encode($package . '/rules/unused-classes.neon', JSON_THROW_ON_ERROR);
        $selection = json_encode($classes, JSON_THROW_ON_ERROR);
        try {
            $filesystem->dumpFile($fixture, "<?php\nnamespace Example;\n" . $source);
            $filesystem->dumpFile($directory . '/phpstan.neon', <<<NEON
                includes:
                    - {$extension}
                parameters:
                    customRulesetUsed: true
                    paths: [source]
                    tmpDir: cache
                    parallel:
                        maximumNumberOfProcesses: 1
                    unusedClasses: {$selection}
                NEON);
            $process = new Process([
                PHP_BINARY, $package . '/vendor/bin/phpstan', 'analyse',
                '--configuration=' . $directory . '/phpstan.neon',
                '--autoload-file=' . $package . '/vendor/autoload.php',
                '--error-format=raw', '--no-progress', '--memory-limit=512M',
            ], $package);

            $exitCode = $process->run();

            self::assertSame($expected === [] ? 0 : 1, $exitCode, $process->getErrorOutput());
            self::assertSame(implode("\n", array_map(static fn (string $error): string => $error . ' [identifier=customRules.unusedClass]', $expected)), trim(str_replace($fixture . ':', '', $process->getOutput())));
        } finally {
            $filesystem->remove($directory);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function providerClassReferences(): iterable
    {
        yield 'without constructor' => [
            'final class Unused {}',
            ['Example\Unused'],
            ['3:Unused class Example\Unused: no reference from outside the configured classes.'],
        ];
        yield 'with constructor' => [
            'final class Unused { public function __construct() {} }',
            ['Example\Unused'],
            ['3:Unused class Example\Unused: no reference from outside the configured classes.'],
        ];
        yield 'vendor interface does not imply use' => [
            <<<'PHP'
                final class Unused implements \Eris\Generator {
                    public function __invoke($size, \Eris\Random\RandomRange $rand) { return \Eris\Generator\GeneratedValueSingle::fromJustValue(1); }
                    public function shrink(\Eris\Generator\GeneratedValue $value) { return $value; }
                }
                PHP,
            ['Example\Unused'],
            ['3:Unused class Example\Unused: no reference from outside the configured classes.'],
        ];
        yield 'direct construction without constructor' => [
            'final class Used {} new Used();',
            ['Example\Used'],
            [],
        ];
        yield 'composed generators' => [
            <<<'PHP'
                final class ParentGenerator { public function __invoke() { return new ChildGenerator(); } }
                final class ChildGenerator {}
                final class Property { public function satisfiesExecution() { return new ParentGenerator(); } }
                PHP,
            ['Example\*Generator'],
            [],
        ];
        yield 'unreferenced composition' => [
            <<<'PHP'
                final class ParentGenerator { public function __construct() { new ChildGenerator(); } }
                final class ChildGenerator {}
                PHP,
            ['Example\*Generator'],
            [
                '3:Unused class Example\ParentGenerator: no reference from outside the configured classes.',
                '4:Unused class Example\ChildGenerator: no reference from outside the configured classes.',
            ],
        ];
        yield 'disconnected cycle' => [
            <<<'PHP'
                final class First { public function make() { return new Second(); } }
                final class Second { public function make() { return new First(); } }
                PHP,
            ['Example\*'],
            [
                '3:Unused class Example\First: no reference from outside the configured classes.',
                '4:Unused class Example\Second: no reference from outside the configured classes.',
            ],
        ];
        yield 'reachable cycle' => [
            <<<'PHP'
                final class First { public function make() { return new Second(); } }
                final class Second { public function make() { return new First(); } }
                new First();
                PHP,
            ['Example\*'],
            [],
        ];
        yield 'self references' => [
            'final class Unused { public static function make(): self { return new self(); } }',
            ['Example\Unused'],
            ['3:Unused class Example\Unused: no reference from outside the configured classes.'],
        ];
        yield 'import and PHPDoc alone' => [
            <<<'PHP'
                final class Unused {}
                use Example\Unused as Alias;
                /** @param Alias $value */
                function accept($value): void {}
                PHP,
            ['Example\Unused'],
            ['3:Unused class Example\Unused: no reference from outside the configured classes.'],
        ];
        yield 'exact name and leading separator' => [
            'final class Selected {} final class Unselected {}',
            ['\eXaMpLe\SeLeCtEd'],
            ['3:Unused class Example\Selected: no reference from outside the configured classes.'],
        ];
        yield 'nested namespace wildcard' => [
            'namespace Example\Nested; final class Unused {}',
            ['Example\*'],
            ['3:Unused class Example\Nested\Unused: no reference from outside the configured classes.'],
        ];
        yield 'empty selection' => ['final class Unused {}', [], []];
        yield 'alias and case insensitive reference' => [
            'final class Used {} use Example\Used as Alias; new ALIAS();',
            ['Example\Used'],
            [],
        ];
        yield 'static factory without constructor' => [
            'final class Used { public static function make(): self { return new self(); } } Used::make();',
            ['Example\Used'],
            [],
        ];
        yield 'constant and static property access' => [
            'final class Used { public const VALUE = 1; public static int $value = 1; } echo Used::VALUE, Used::$value;',
            ['Example\Used'],
            [],
        ];
        yield 'class name constant' => [
            'final class Used {} $name = Used::class;',
            ['Example\Used'],
            [],
        ];
        yield 'resolved dynamic construction' => [
            'final class Used {} $name = "Example\\Used"; new $name();',
            ['Example\Used'],
            [],
        ];
        yield 'unresolved dynamic construction does not mark every class used' => [
            'final class Unused {} function create(string $name) { return new $name(); }',
            ['Example\Unused'],
            ['3:Unused class Example\Unused: no reference from outside the configured classes.'],
        ];
        yield 'native parameter property and return types' => [
            <<<'PHP'
                class Input {} class Output {} class Stored {}
                class Consumer { public ?Stored $value = null; public function convert(Input $value): Output { throw new \LogicException(); } }
                PHP,
            ['Example\Input', 'Example\Output', 'Example\Stored'],
            [],
        ];
        yield 'inheritance and trait composition' => [
            <<<'PHP'
                interface Contract {} class ParentClass {} trait Behavior {}
                class Child extends ParentClass implements Contract { use Behavior; }
                new Child();
                PHP,
            ['Example\*'],
            [],
        ];
        yield 'unreferenced inheritance' => [
            <<<'PHP'
                class ParentClass {}
                class Child extends ParentClass {}
                PHP,
            ['Example\*'],
            [
                '3:Unused class Example\ParentClass: no reference from outside the configured classes.',
                '4:Unused class Example\Child: no reference from outside the configured classes.',
            ],
        ];
        yield 'attribute usage' => [
            <<<'PHP'
                #[\Attribute] class Marker {}
                #[Marker] class Consumer {}
                PHP,
            ['Example\Marker'],
            [],
        ];
        yield 'attribute on an unreferenced class' => [
            <<<'PHP'
                #[\Attribute] class Marker {}
                #[Marker] class Consumer {}
                PHP,
            ['Example\*'],
            [
                '3:Unused class Example\Marker: no reference from outside the configured classes.',
                '4:Unused class Example\Consumer: no reference from outside the configured classes.',
            ],
        ];
        yield 'enum case' => [
            'enum Choice { case First; } $value = Choice::First;',
            ['Example\Choice'],
            [],
        ];
        yield 'native union and intersection types' => [
            'interface First {} interface Second {} class Alternative {} function accept(First&Second $value): First|Alternative { return $value; }',
            ['Example\*'],
            [],
        ];
        yield 'resolved dynamic static access' => [
            'final class Used { public static function run(): void {} } $name = "Example\\Used"; $name::run();',
            ['Example\Used'],
            [],
        ];
        yield 'instance method and property access with resolved PHPDoc type' => [
            <<<'PHP'
                class Used { public int $value = 1; public function run(): void {} }
                /** @param Used $value */
                function access($value) { $value->run(); return $value->value; }
                PHP,
            ['Example\Used'],
            [],
        ];
        yield 'nullsafe access with resolved PHPDoc type' => [
            <<<'PHP'
                class Used { public int $value = 1; public function run(): void {} }
                /** @param Used|null $value */
                function access($value) { $value?->run(); return $value?->value; }
                PHP,
            ['Example\Used'],
            [],
        ];
        yield 'static access through an instance with resolved PHPDoc type' => [
            <<<'PHP'
                class Used { public static function run(): void {} }
                /** @param Used $value */
                function access($value) { $value::run(); }
                PHP,
            ['Example\Used'],
            [],
        ];
        yield 'interface call does not imply use of every implementation' => [
            <<<'PHP'
                interface Contract { public function run(): void; }
                class Unused implements Contract { public function run(): void {} }
                function access(Contract $value) { $value->run(); }
                PHP,
            ['Example\Unused'],
            ['4:Unused class Example\Unused: no reference from outside the configured classes.'],
        ];
        yield 'instanceof and catch' => [
            'class Checked {} class Failure extends \Exception {} function check($value) { try { return $value instanceof Checked; } catch (Failure $error) { return false; } }',
            ['Example\Checked', 'Example\Failure'],
            [],
        ];
    }
}
