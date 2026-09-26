<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Requirements\Console\Options;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Item;
use Requirements\Model\Source;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;

#[CoversClass(Options::class)]
#[UsesClass(Fields::class)]
#[UsesClass(Item::class)]
#[UsesClass(Source::class)]
#[Small]
final class OptionsTest extends TestCase
{
    public function testFromInputReadsGlobalAndCommandOptions(): void
    {
        $definition = new InputDefinition([
            new InputOption('config', null, InputOption::VALUE_REQUIRED, '', 'requirements.yaml'),
            new InputOption('json', null, InputOption::VALUE_NONE),
            new InputOption('live', null, InputOption::VALUE_NONE),
            new InputOption('snapshot', null, InputOption::VALUE_REQUIRED),
            new InputOption('write-snapshot', null, InputOption::VALUE_REQUIRED),
            new InputOption('min-coverage', null, InputOption::VALUE_REQUIRED),
            new InputOption('min-diff-coverage', null, InputOption::VALUE_REQUIRED),
            new InputOption('allow-removed', null, InputOption::VALUE_NONE),
        ]);
        $options = Options::fromInput('coverage', new ArrayInput(['--json' => true, '--snapshot' => 'base.json', '--min-coverage' => '80'], $definition));
        self::assertSame('coverage', $options->command);
        self::assertSame(['config' => 'requirements.yaml', 'json' => true, 'live' => false, 'snapshot' => 'base.json', 'min-coverage' => '80', 'allow-removed' => false], $options->values);
    }

    public function testFromInputReadsOnlyTheGlobalOptionsOfACommandWithoutOptions(): void
    {
        $definition = new InputDefinition([
            new InputOption('config', null, InputOption::VALUE_REQUIRED, '', 'requirements.yaml'),
            new InputOption('json', null, InputOption::VALUE_NONE),
        ]);
        $options = Options::fromInput('lint', new ArrayInput(['--config' => 'other.yaml'], $definition));
        self::assertSame('lint', $options->command);
        self::assertSame(['config' => 'other.yaml', 'json' => false], $options->values);
    }

    public function testFromInputSkipsValuesThatAreNeitherTextNorFlags(): void
    {
        $definition = new InputDefinition([
            new InputOption('config', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
            new InputOption('json', null, InputOption::VALUE_NONE),
            new InputOption('check', null, InputOption::VALUE_NONE),
        ]);
        $options = Options::fromInput('format', new ArrayInput(['--config' => ['a.yaml', 'b.yaml'], '--check' => true], $definition));
        self::assertSame(['json' => false, 'check' => true], $options->values);
    }

    /**
     * @param list<array{string, bool, string}> $expected
     */
    #[DataProvider('providerDefinitions')]
    public function testDefinitionsDeclaresTheOptionsOfEachCommand(string $command, array $expected): void
    {
        self::assertSame($expected, Options::definitions($command));
    }

    /**
     * @return array<string, array{string, list<array{string, bool, string}>}>
     */
    public static function providerDefinitions(): array
    {
        return [
            'spec' => ['spec', [
                ['no-test', true, 'Display selected records and linked test counts without running tests.'],
                ['strict', true, 'Fail when no records match or a selected supported specification has no linked tests.'],
                ['all', true, 'Include manual tests linked to selected supported specifications.'],
                ['id', false, 'Select an exact item ID.'],
                ['label', false, 'Select items carrying this label.'],
                ['category', false, 'Select an exact category.'],
                ['source', false, 'Select a definition source ID.'],
                ['status', false, 'Select supported or unsupported items.'],
                ['kind', false, 'Select specification or requirement items.'],
                ['origin', false, 'Select sourced, original or undocumented items.'],
                ['without-source', true, 'Select independent original or undocumented items.'],
            ]],
            'check' => ['check', [['live', true, 'Fetch current source URIs instead of pinned local snapshots.']]],
            'coverage' => ['coverage', [
                ['live', true, 'Fetch current source URIs instead of pinned local snapshots.'],
                ['snapshot', false, 'Compare with the coverage snapshot of a trusted base revision to find new, changed and removed units.'],
                ['write-snapshot', false, 'Write a coverage snapshot (every unit in scope with its fingerprint, no source text) to this JSON file.'],
                ['min-coverage', false, 'Override the overall coverage threshold (0–100).'],
                ['min-diff-coverage', false, 'Override the coverage threshold for units that are new or changed since the snapshot (0–100).'],
                ['allow-removed', true, 'Accept units that the snapshot lists but the current scope no longer contains.'],
            ]],
            'format' => ['format', [['check', true, 'Report formatting differences without writing files.']]],
            'lint' => ['lint', []],
            'unknown' => ['unknown', []],
        ];
    }

    /**
     * @param array<string, string|bool> $values
     */
    #[DataProvider('providerText')]
    public function testTextReturnsTheValueOrTheDefault(array $values, ?string $default, ?string $expected): void
    {
        self::assertSame($expected, (new Options('spec', $values))->text('id', $default));
    }

    /**
     * @return array<string, array{array<string, string|bool>, string|null, string|null}>
     */
    public static function providerText(): array
    {
        return [
            'given' => [['id' => 'SPEC-001'], 'DEFAULT', 'SPEC-001'],
            'given without default' => [['id' => 'SPEC-001'], null, 'SPEC-001'],
            'empty text' => [['id' => ''], 'DEFAULT', ''],
            'missing with default' => [[], 'DEFAULT', 'DEFAULT'],
            'missing without default' => [[], null, null],
            'flag with default' => [['id' => true], 'DEFAULT', 'DEFAULT'],
            'flag without default' => [['id' => true], null, null],
        ];
    }

    /**
     * @param array<string, string|bool> $values
     */
    #[DataProvider('providerFlag')]
    public function testFlagTellsWhetherTheFlagIsSet(array $values, bool $expected): void
    {
        self::assertSame($expected, (new Options('spec', $values))->flag('no-test'));
    }

    /**
     * @return array<string, array{array<string, string|bool>, bool}>
     */
    public static function providerFlag(): array
    {
        return [
            'set' => [['no-test' => true], true],
            'unset' => [['no-test' => false], false],
            'missing' => [[], false],
            'text' => [['no-test' => '1'], false],
        ];
    }

    /**
     * @param array<string, string|bool> $values
     */
    #[DataProvider('providerPercentage')]
    public function testPercentageReturnsTheNumber(array $values, ?float $expected): void
    {
        self::assertSame($expected, (new Options('coverage', $values))->percentage('min-coverage'));
    }

    /**
     * @return array<string, array{array<string, string|bool>, float|null}>
     */
    public static function providerPercentage(): array
    {
        return [
            'missing' => [[], null],
            'flag' => [['min-coverage' => true], null],
            'integer' => [['min-coverage' => '80'], 80.0],
            'decimal' => [['min-coverage' => '12.5'], 12.5],
            'zero' => [['min-coverage' => '0'], 0.0],
            'hundred' => [['min-coverage' => '100'], 100.0],
        ];
    }

    #[DataProvider('providerPercentageRejected')]
    public function testPercentageRejectsInvalidValues(string $value, string $message): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        (new Options('coverage', ['min-diff-coverage' => $value]))->percentage('min-diff-coverage');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function providerPercentageRejected(): array
    {
        return [
            'not a number' => ['not-a-number', '--min-diff-coverage requires a percentage.'],
            'empty' => ['', '--min-diff-coverage requires a percentage.'],
            'above' => ['100.5', 'min-diff-coverage must be a number from 0 to 100.'],
            'below' => ['-1', 'min-diff-coverage must be a number from 0 to 100.'],
        ];
    }

    /**
     * @param array<string, string|bool> $values
     */
    #[DataProvider('providerMatchesSourcedItem')]
    public function testMatchesFiltersASourcedItem(array $values, bool $expected): void
    {
        $item = new Item('SPEC-001', 'specification', 'The reader shall preserve positions.', 'supported', new Source('manual', 'source.html', 'html', 'main p'), [], [], [], [], ['diagnostics', 'reader'], 'parsing', 'sourced', '', 'definition.yaml', []);
        self::assertSame($expected, (new Options('spec', $values))->matches($item));
    }

    /**
     * @return array<string, array{array<string, string|bool>, bool}>
     */
    public static function providerMatchesSourcedItem(): array
    {
        return [
            'no filter' => [[], true],
            'flag false' => [['no-test' => true, 'without-source' => false], true],
            'id' => [['id' => 'SPEC-001'], true],
            'other id' => [['id' => 'SPEC-002'], false],
            'id prefix' => [['id' => 'SPEC'], false],
            'category' => [['category' => 'parsing'], true],
            'other category' => [['category' => 'reader'], false],
            'status' => [['status' => 'supported'], true],
            'other status' => [['status' => 'unsupported'], false],
            'kind' => [['kind' => 'specification'], true],
            'other kind' => [['kind' => 'requirement'], false],
            'origin' => [['origin' => 'sourced'], true],
            'other origin' => [['origin' => 'original'], false],
            'source' => [['source' => 'manual'], true],
            'other source' => [['source' => 'missing'], false],
            'first label' => [['label' => 'diagnostics'], true],
            'second label' => [['label' => 'reader'], true],
            'other label' => [['label' => 'strictness'], false],
            'every filter' => [['id' => 'SPEC-001', 'category' => 'parsing', 'status' => 'supported', 'kind' => 'specification', 'origin' => 'sourced', 'source' => 'manual', 'label' => 'reader'], true],
            'every filter but the last' => [['id' => 'SPEC-001', 'category' => 'parsing', 'status' => 'supported', 'kind' => 'specification', 'origin' => 'sourced', 'source' => 'other'], false],
            'every filter but the label' => [['id' => 'SPEC-001', 'category' => 'parsing', 'label' => 'other'], false],
            'without source' => [['without-source' => true], false],
        ];
    }

    /**
     * @param array<string, string|bool> $values
     */
    #[DataProvider('providerMatchesIndependentItem')]
    public function testMatchesFiltersAnIndependentItem(array $values, bool $expected): void
    {
        $item = new Item('ORIGINAL-001', 'specification', 'The parser shall reject truncated input.', 'supported', null, [], [], [], [], ['strictness'], '', 'original', 'Avoid silent data loss.', 'definition.yaml', []);
        self::assertSame($expected, (new Options('spec', $values))->matches($item));
    }

    /**
     * @return array<string, array{array<string, string|bool>, bool}>
     */
    public static function providerMatchesIndependentItem(): array
    {
        return [
            'without source' => [['without-source' => true], true],
            'without source and label' => [['without-source' => true, 'label' => 'strictness'], true],
            'without source and other label' => [['without-source' => true, 'label' => 'grammar'], false],
            'any source' => [['source' => 'manual'], false],
            'empty category' => [['category' => ''], true],
            'origin' => [['origin' => 'original'], true],
        ];
    }
}
