<?php

declare(strict_types=1);

namespace Deriver\Configuration;

/**
 * Output formats selectable in configuration and on the command line.
 * @visibility public
 * @example Selecting JSON output
 *     \Deriver\Configuration\ReportFormat::Json->value // => 'json'
 */
enum ReportFormat: string
{
    case Text = 'text';
    case Json = 'json';
}
