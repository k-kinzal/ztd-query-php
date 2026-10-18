<?php

declare(strict_types=1);

namespace Deriver\Command;

use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * Symfony Console application for the deliver executable.
 * @visibility public
 * @example Inspecting the executable name
 *     (new \Deriver\Command\Application())->getName() // => 'deliver'
 */
final class Application extends ConsoleApplication
{
    /**
     * Makes derive the single command so callers supply only the selector and options.
     */
    public function __construct()
    {
        parent::__construct('deliver');
        $this->add(new DeriveCommand());
        $this->setDefaultCommand('deliver', true);
    }
}
