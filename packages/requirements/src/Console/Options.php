<?php

declare(strict_types=1);

namespace Requirements\Console;

use InvalidArgumentException;
use Requirements\Config\Fields;
use Requirements\Model\Item;
use Symfony\Component\Console\Input\InputInterface;

final class Options
{
    /** @param array<string, string|bool> $values */
    public function __construct(public readonly string $command, public readonly array $values)
    {
    }

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

    /** @return list<array{string, bool, string}> */
    public static function definitions(string $command): array
    {
        return match ($command) {
            'list', 'spec' => [
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
                ['baseline', false, 'Compare with the fingerprint baseline from a trusted base revision.'],
                ['write-baseline', false, 'Write the current source-unit fingerprints to this JSON file.'],
                ['min-coverage', false, 'Override the overall coverage threshold (0–100).'],
                ['min-diff-coverage', false, 'Override the changed-unit coverage threshold (0–100).'],
                ['allow-removed', true, 'Accept reviewed removals from the source scope.'],
            ],
            'format' => [['check', true, 'Report formatting differences without writing files.']],
            default => [],
        };
    }

    public function text(string $name, ?string $default = null): ?string
    {
        $value = $this->values[$name] ?? $default;
        return is_string($value) ? $value : $default;
    }

    public function flag(string $name): bool
    {
        return ($this->values[$name] ?? false) === true;
    }

    public function percentage(string $name): ?float
    {
        $value = $this->text($name);
        if ($value === null) {
            return null;
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("--$name requires a percentage.");
        }
        return Fields::percentage((float) $value, $name);
    }

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
