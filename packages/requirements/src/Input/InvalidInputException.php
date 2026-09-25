<?php

declare(strict_types=1);

namespace Requirements\Input;

use RuntimeException;

/**
 * Reports configuration, definitions, snapshots or command-line options that cannot be accepted.
 *
 * The message names the offending file, item or option and states what is expected, so the
 * command line can show it to the author unchanged.
 */
final class InvalidInputException extends RuntimeException
{
}
