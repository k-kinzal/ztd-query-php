<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Exception\InvalidInputException;

/**
 * The syntax of a selector, independent of source files and analysis state.
 * @visibility public
 * @example Parsing a return selector
 *     \Deriver\Query::parse('App\\answer#return')->target // => 'App\\answer'
 * @example Formatting a source selector
 *     \Deriver\Query::parse('src/Repository.php:25:5#argument(0)')->toString() // => 'src/Repository.php:25:5#argument(0)'
 */
final class Query
{
    /**
     * @param string $target Callable name or unquoted source path
     * @param int|null $line One-based source line, or null for a callable
     * @param int|null $column One-based byte column, or null for a callable
     * @param string $selector Requested part of the target
     * @param list<int|string> $arguments Selector arguments, not application inputs
     */
    private function __construct(
        public readonly string $target,
        public readonly ?int $line,
        public readonly ?int $column,
        public readonly string $selector,
        public readonly array $arguments,
    ) {
    }

    /**
     * Extracts target, optional coordinates, selector and arguments without reading source.
     * @throws InvalidInputException If the selector syntax or integer range is invalid
     */
    public static function parse(string $query): self
    {
        $query = trim($query);
        if (preg_match('/[\x00-\x1f\x7f]/', $query) === 1 || preg_match('/\A(.+)#([a-z][a-z0-9]*)(?:\(([^()]*)\))?\z/', $query, $parts) !== 1) {
            throw new InvalidInputException('Expected target#selector or target#selector(arguments).');
        }
        $target = trim($parts[1]);
        $line = $column = null;
        if (preg_match('/\A(.*):([0-9]+):([0-9]+)\z/', $target, $position) === 1) {
            $target = $position[1];
            $line = (int) $position[2];
            $column = (int) $position[3];
            if ($line < 1 || $column < 1 || (string) $line !== $position[2] || (string) $column !== $position[3]) {
                throw new InvalidInputException('Source coordinates must be positive integers without leading zeroes.');
            }
        }
        if ($target === '' || str_contains($target, '"') || str_contains($target, "'")) {
            throw new InvalidInputException('Query targets must be nonempty and unquoted.');
        }
        $arguments = [];
        $input = trim($parts[3] ?? '');
        foreach ($input === '' ? [] : explode(',', $input) as $token) {
            $token = trim($token);
            if (preg_match('/\A(?:0|[1-9][0-9]*|[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*)\z/', $token) !== 1) {
                throw new InvalidInputException('Selector arguments must be identifiers or nonnegative integers.');
            }
            $numeric = preg_match('/\A[0-9]/', $token) === 1;
            if ($numeric && (string) (int) $token !== $token) {
                throw new InvalidInputException('Selector argument exceeds the supported integer range.');
            }
            $arguments[] = $numeric ? (int) $token : $token;
        }
        return new self($target, $line, $column, $parts[2], $arguments);
    }

    /**
     * Formats the parsed fields with canonical spacing and no empty parentheses.
     */
    public function toString(): string
    {
        $position = $this->line === null ? '' : ':' . $this->line . ':' . $this->column;
        $arguments = $this->arguments === [] ? '' : '(' . implode(',', $this->arguments) . ')';
        return $this->target . $position . '#' . $this->selector . $arguments;
    }
}
