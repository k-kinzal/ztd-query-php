<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Reports that concrete SQL did not preserve the derived parser-token sequence.
 *
 * Tokenizing is applied to SQL text, so a mismatch describes the input rather
 * than a defect in the lexer, and callers are expected to handle it.
 */
final class LexicalException extends RuntimeException
{
}
