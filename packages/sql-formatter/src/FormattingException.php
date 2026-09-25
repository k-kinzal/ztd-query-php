<?php

declare(strict_types=1);

namespace SqlFormatter;

use RuntimeException;

/**
 * A layout failed verification against the original concrete syntax tree.
 *
 * @example Reading a verification failure
 *     $error = new \SqlFormatter\FormattingException('Syntax changed.');
 *     $error->getMessage() // => 'Syntax changed.'
 *
 * @visibility public
 */
final class FormattingException extends RuntimeException
{
}
