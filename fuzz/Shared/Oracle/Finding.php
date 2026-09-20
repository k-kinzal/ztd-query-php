<?php

declare(strict_types=1);

namespace Fuzz\Shared\Oracle;

use RuntimeException;

/**
 * A violated ZTD contract, distinct from an expected database rejection.
 */
final class Finding extends RuntimeException
{
}
