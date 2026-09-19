<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

/**
 * Everything one run of the command produced.
 *
 * Holding the output instead of writing it keeps the command testable without
 * capturing streams.
 *
 * @visibility root
 */
final class CommandResult
{
    /**
     * @param ExitCode $exitCode What to tell the shell
     * @param string $output What to write to standard output
     * @param string $error What to write to standard error
     */
    public function __construct(
        public readonly ExitCode $exitCode,
        public readonly string $output = '',
        public readonly string $error = '',
    ) {
    }

    /**
     * A result that only writes to standard output.
     */
    public static function ok(string $output): self
    {
        return new self(ExitCode::Success, $output);
    }

    /**
     * A result that reports a problem and nothing else.
     */
    public static function failure(ExitCode $exitCode, string $error): self
    {
        return new self($exitCode, '', rtrim($error, "\n") . "\n");
    }
}
