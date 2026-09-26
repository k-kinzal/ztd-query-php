<?php

declare(strict_types=1);

namespace Requirements\Console;

use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use Requirements\Model\Item;
use Symfony\Component\Console\Input\InputInterface;

/**
 * The command and the option values given on the command line.
 */
final class Options
{
    /**
     * @param string $command The command name
     * @param array<string, string|bool> $values Option values by option name
     */
    public function __construct(public readonly string $command, public readonly array $values)
    {
    }

    /**
     * Reads the global options and the options of a command.
     *
     * @param string $command The command name
     * @param InputInterface $input The parsed command line
     *
     * @return self The options
     */
    public static function fromInput(string $command, InputInterface $input): self
    {
        $values = [];
        foreach (['config', 'json', ...array_column(self::definitions($command), 0)] as $key) {
            $value = $input->getOption($key);
            if (is_string($value) || is_bool($value)) {
                $values[$key] = $value;
            }
        }
        return new self($command, $values);
    }

    /**
     * Declares the options of a command.
     *
     * @param string $command The command name
     *
     * @return list<array{string, bool, string}> The name, whether it is a flag, and the description of each option
     */
    public static function definitions(string $command): array
    {
        return match ($command) {
            'spec' => [
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
            ],
            'check' => [['live', true, 'Fetch current source URIs instead of pinned local snapshots.']],
            'coverage' => [
                ['live', true, 'Fetch current source URIs instead of pinned local snapshots.'],
                ['snapshot', false, 'Compare with the coverage snapshot of a trusted base revision to find new, changed and removed units.'],
                ['write-snapshot', false, 'Write a coverage snapshot (every unit in scope with its fingerprint, no source text) to this JSON file.'],
                ['min-coverage', false, 'Override the overall coverage threshold (0–100).'],
                ['min-diff-coverage', false, 'Override the coverage threshold for units that are new or changed since the snapshot (0–100).'],
                ['allow-removed', true, 'Accept units that the snapshot lists but the current scope no longer contains.'],
            ],
            'format' => [['check', true, 'Report formatting differences without writing files.']],
            default => [],
        };
    }

    /**
     * Returns a value option.
     *
     * @param string $name The option name
     * @param string|null $default The value when the option is not given
     *
     * @return string|null The value
     */
    public function text(string $name, ?string $default = null): ?string
    {
        $value = $this->values[$name] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /**
     * Tells whether a flag is set.
     *
     * @param string $name The option name
     *
     * @return bool True when the flag was given
     */
    public function flag(string $name): bool
    {
        return ($this->values[$name] ?? false) === true;
    }

    /**
     * Returns a percentage option.
     *
     * @param string $name The option name
     *
     * @return float|null The percentage, or null when the option is not given
     *
     * @throws InvalidInputException When the value is not a number from 0 to 100
     */
    public function percentage(string $name): ?float
    {
        $value = $this->text($name);
        if ($value === null) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new InvalidInputException("--$name requires a percentage.");
        }
        return Fields::percentage((float) $value, $name);
    }

    /**
     * Tells whether an item passes the spec filters.
     *
     * @param Item $item The item
     *
     * @return bool True when the item has every given ID, category, status, kind, origin, source and label, and no source under --without-source
     */
    public function matches(Item $item): bool
    {
        foreach (['id' => $item->id, 'category' => $item->category, 'status' => $item->status, 'kind' => $item->kind, 'origin' => $item->origin, 'source' => $item->source?->id] as $key => $actual) {
            if ($this->text($key) !== null && $this->text($key) !== $actual) {
                return false;
            }
        }
        $label = $this->text('label');
        return ($label === null || in_array($label, $item->labels, true)) && (!$this->flag('without-source') || $item->origin !== 'sourced');
    }
}
