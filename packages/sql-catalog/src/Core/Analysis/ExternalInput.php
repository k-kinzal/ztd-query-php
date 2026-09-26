<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis;

/**
 * The places a value can enter the program from outside it.
 *
 * A gap the analyzer can trace back to one of these is the difference between
 * a query that is merely dynamic and one an attacker can steer.
 *
 * @visibility root
 */
final class ExternalInput
{
    private const VARIABLES = [
        '_GET' => true,
        '_POST' => true,
        '_REQUEST' => true,
        '_COOKIE' => true,
        '_SERVER' => true,
        '_FILES' => true,
        '_ENV' => true,
        '_SESSION' => true,
        'GLOBALS' => true,
        'argv' => true,
    ];

    private const FUNCTIONS = [
        'getenv' => true,
        'filter_input' => true,
        'filter_input_array' => true,
        'readline' => true,
        'fgets' => true,
        'stream_get_contents' => true,
        'apache_request_headers' => true,
        'getallheaders' => true,
    ];

    /**
     * Whether reading that variable reads something from outside the program.
     */
    public function isVariable(string $name): bool
    {
        return isset(self::VARIABLES[$name]);
    }

    /**
     * Whether calling that function reads something from outside the program.
     */
    public function isFunction(string $name): bool
    {
        return isset(self::FUNCTIONS[strtolower(ltrim($name, '\\'))]);
    }

    /**
     * Whether reading that file reads the request body.
     */
    public function isStream(string $target): bool
    {
        return str_starts_with($target, 'php://input') || str_starts_with($target, 'php://stdin');
    }
}
