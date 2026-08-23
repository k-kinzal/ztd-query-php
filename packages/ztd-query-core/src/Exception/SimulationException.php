<?php

declare(strict_types=1);

namespace ZtdQuery\Exception;

use RuntimeException;

/**
 * Base type for failures produced by ZTD simulation rather than the driver.
 */
abstract class SimulationException extends RuntimeException
{
}
