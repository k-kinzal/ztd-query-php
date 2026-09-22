<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

/**
 * What the command tells the shell about how the run went.
 *
 * @visibility root
 */
enum ExitCode: int
{
    case Success = 0;
    case FindingsReported = 1;
    case InvalidCommandLine = 2;
    case SourceUnreadable = 3;
}
