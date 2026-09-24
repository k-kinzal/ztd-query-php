<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

use SqlCatalog\Catalog\Severity;
use SqlCatalog\Filter\CatalogFilter;
use SqlCatalog\Sql\StatementKind;

/**
 * Reads the arguments the command was invoked with.
 *
 * @visibility root
 */
final class CommandLineParser
{
    private const VALUE_OPTIONS = [
        'config' => 'config',
        'c' => 'config',
        'output' => 'output',
        'o' => 'output',
        'reporter' => 'reporter',
        'r' => 'reporter',
        'extension' => 'extension',
        'e' => 'extension',
        'namespace' => 'namespace',
        'method' => 'method',
        'path' => 'path',
        'kind' => 'kind',
        'table' => 'table',
        'sink' => 'sink',
        'severity' => 'severity',
        'exclude' => 'exclude',
        'root' => 'root',
        'fail-on' => 'fail-on',
    ];

    private const FLAG_OPTIONS = [
        'help' => 'help',
        'h' => 'help',
        'list-extensions' => 'list-extensions',
        'list-reporters' => 'list-reporters',
    ];

    /**
     * The command line the arguments describe.
     *
     * @param list<string> $arguments The arguments, without the command name
     * @throws InvalidCommandLineException When an argument is not one the command takes
     */
    public function parse(array $arguments): CommandLine
    {
        $values = [];
        $flags = [];
        $paths = [];

        $count = count($arguments);
        for ($index = 0; $index < $count; $index++) {
            $argument = $arguments[$index];
            if ($argument === '--' || !str_starts_with($argument, '-') || $argument === '-') {
                $paths[] = $argument;
                continue;
            }
            $index = $this->readOption($argument, $arguments, $index, $values, $flags);
        }

        return $this->build($values, $flags, $paths);
    }

    /**
     * Reads one option, consuming the argument after it when the option takes a value.
     *
     * @param list<string> $arguments
     * @param array<string, list<string>> $values
     * @param array<string, bool> $flags
     * @throws InvalidCommandLineException When the option is unknown or its value is missing
     */
    public function readOption(string $argument, array $arguments, int $index, array &$values, array &$flags): int
    {
        $written = ltrim($argument, '-');
        $inline = null;
        if (str_contains($written, '=')) {
            [$written, $inline] = explode('=', $written, 2);
        }

        if (isset(self::FLAG_OPTIONS[$written]) && $inline === null) {
            $flags[self::FLAG_OPTIONS[$written]] = true;

            return $index;
        }
        $option = self::VALUE_OPTIONS[$written] ?? null;
        if ($option === null) {
            throw new InvalidCommandLineException(sprintf('Unknown option "%s".', $argument));
        }

        $value = $inline ?? ($arguments[$index + 1] ?? null);
        if ($value === null || ($inline === null && str_starts_with($value, '-'))) {
            throw new InvalidCommandLineException(sprintf('Option "%s" needs a value.', $argument));
        }
        foreach ($option === 'config' ? [$value] : explode(',', $value) as $part) {
            $values[$option][] = trim($part);
        }

        return $inline === null ? $index + 1 : $index;
    }

    /**
     * The command line built from the options that were read.
     *
     * @param array<string, list<string>> $values
     * @param array<string, bool> $flags
     * @param list<string> $paths
     * @throws InvalidCommandLineException When an option was given a value it does not take
     */
    public function build(array $values, array $flags, array $paths): CommandLine
    {
        $output = $this->last($values, 'output');

        return new CommandLine(
            $paths,
            $output,
            $this->last($values, 'reporter') ?? ($output === null ? 'text' : 'json'),
            $values['extension'] ?? ['pdo', 'mysqli'],
            $this->filter($values),
            $values['exclude'] ?? [],
            $this->last($values, 'root') ?? '.',
            $this->severity($values, 'fail-on'),
            $flags['help'] ?? false,
            $flags['list-extensions'] ?? false,
            $flags['list-reporters'] ?? false,
            $this->last($values, 'config'),
        );
    }

    /**
     * The filter the filtering options describe.
     *
     * @param array<string, list<string>> $values
     * @throws InvalidCommandLineException When a kind or severity is not one the command knows
     */
    public function filter(array $values): CatalogFilter
    {
        return new CatalogFilter(
            $values['namespace'] ?? [],
            $values['method'] ?? [],
            $values['path'] ?? [],
            $this->kinds($values['kind'] ?? []),
            $values['table'] ?? [],
            $values['sink'] ?? [],
            $this->severity($values, 'severity'),
        );
    }

    /**
     * The statement kinds the written names stand for.
     *
     * @param list<string> $written
     * @return list<StatementKind>
     * @throws InvalidCommandLineException When a name is not a statement kind
     */
    public function kinds(array $written): array
    {
        $kinds = [];
        foreach ($written as $name) {
            $kind = StatementKind::tryFrom(strtolower($name));
            if ($kind === null) {
                throw new InvalidCommandLineException(sprintf(
                    'Unknown statement kind "%s". Known kinds: %s.',
                    $name,
                    implode(', ', array_column(StatementKind::cases(), 'value')),
                ));
            }
            $kinds[] = $kind;
        }

        return $kinds;
    }

    /**
     * The severity an option was given, or null when it was not given.
     *
     * @param array<string, list<string>> $values
     * @throws InvalidCommandLineException When the name is not a severity
     */
    public function severity(array $values, string $option): ?Severity
    {
        $written = $this->last($values, $option);
        if ($written === null) {
            return null;
        }
        $severity = Severity::tryFrom(strtolower($written));
        if ($severity === null) {
            throw new InvalidCommandLineException(sprintf(
                'Unknown severity "%s". Known severities: %s.',
                $written,
                implode(', ', array_column(Severity::cases(), 'value')),
            ));
        }

        return $severity;
    }

    /**
     * The last value an option was given, or null when it was not given.
     *
     * @param array<string, list<string>> $values
     */
    public function last(array $values, string $option): ?string
    {
        $given = $values[$option] ?? [];

        return $given === [] ? null : $given[count($given) - 1];
    }
}
