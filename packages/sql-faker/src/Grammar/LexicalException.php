<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use LogicException;

/**
 * Reports that concrete SQL did not preserve the derived parser-token sequence.
 */
final class LexicalException extends LogicException
{
}
