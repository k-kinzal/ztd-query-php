<?php

declare(strict_types=1);

namespace Requirements\Console;

use InvalidArgumentException;
use Requirements\Config\Fields;
use Requirements\Model\Item;

final class Options
{
    /** @param array<string, string|bool> $values */
    public function __construct(public readonly string $command, public readonly array $values)
    {
    }

    /** @param list<string> $arguments */
    public static function parse(array $arguments): self
    {
        $command = array_shift($arguments) ?? 'help';
        $values = [];
        $flags = ['json', 'live', 'without-source', 'allow-removed', 'check'];
        $strings = ['config', 'id', 'label', 'category', 'source', 'status', 'kind', 'origin', 'baseline', 'write-baseline', 'min-coverage', 'min-diff-coverage'];
        while ($arguments !== []) {
            $argument = array_shift($arguments);
            $parts = explode('=', $argument, 2);
            $key = substr($parts[0], 2);
            if (!str_starts_with($argument, '--') || isset($values[$key])) {
                throw new InvalidArgumentException("Invalid or duplicate option: $argument");
            }
            if (in_array($key, $flags, true) && count($parts) === 1) {
                $values[$key] = true;
            } elseif (in_array($key, $strings, true)) {
                $value = $parts[1] ?? array_shift($arguments);
                if ($value === null || $value === '' || str_starts_with($value, '--')) {
                    throw new InvalidArgumentException("--$key requires a value.");
                }
                $values[$key] = $value;
            } else {
                throw new InvalidArgumentException("Unknown option: $argument");
            }
        }
        $allowed = match ($command) {
            'list', 'spec' => ['id', 'label', 'category', 'source', 'status', 'kind', 'origin', 'without-source'],
            'check' => ['live'],
            'coverage' => ['live', 'baseline', 'write-baseline', 'min-coverage', 'min-diff-coverage', 'allow-removed'],
            'format' => ['check'],
            default => [],
        };
        foreach (array_keys($values) as $key) {
            if (!in_array($key, ['config', 'json', ...$allowed], true)) {
                throw new InvalidArgumentException("--$key is not valid for $command.");
            }
        }
        return new self($command, $values);
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
