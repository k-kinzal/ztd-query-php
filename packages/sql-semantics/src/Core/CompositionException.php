<?php

declare(strict_types=1);

namespace SqlSemantics\Core;

use RuntimeException;

/**
 * A value that cannot be composed in the selected language release.
 *
 * The value asked for either has no form in the release, such as a common
 * table expression in a release without them, or it is spelled from data the
 * language cannot hold, such as a string with a byte its literals cannot
 * contain. Which release and which data are decided at run time, so this is
 * a runtime failure the composing code has to expect.
 *
 * @visibility public
 * @example Reporting an impossible composition
 *     $error = new \SqlSemantics\Core\CompositionException('No common table expression in this release');
 *     $error->getMessage() // => 'No common table expression in this release'
 */
final class CompositionException extends RuntimeException
{
}
