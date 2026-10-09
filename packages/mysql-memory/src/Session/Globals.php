<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;

/**
 * The global values of the system variables of one server, and of its named key caches.
 *
 * A variable never set globally holds the default of its definition, or the value the server
 * was started with. A few text variables hold NULL. The parameters of the key cache named
 * `default` are the global variables. Another key cache exists once SET GLOBAL gives one of its
 * parameters a value: its buffer is 0 bytes and its other parameters hold their defaults until
 * they are set. Every parameter of a key cache that does not exist reads 0. Names of key caches
 * are case-sensitive (verified on live 5.6.51, 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/multiple-key-caches.html.
 *
 * @visibility MySqlMemory
 */
final class Globals
{
    /**
     * @param array<string, string|int|null> $values The values set at start or by SET GLOBAL, by lower-case name
     */
    public function __construct(public array $values = [])
    {
    }

    /**
     * @var array<string, array<string, int>> The parameters set of each named key cache, by name of the cache and of the variable
     */
    public array $caches = [];

    /**
     * Answers a parameter of a named key cache: the value set, its default when the cache exists, or 0.
     */
    public function cached(string $cache, Definition $definition): string|int|null
    {
        if ($cache === 'default') {
            return $this->value($definition);
        }
        if (!isset($this->caches[$cache])) {
            return 0;
        }

        return $this->caches[$cache][$definition->name] ?? ($definition->name === 'key_buffer_size' ? 0 : $definition->default);
    }

    /**
     * Sets a parameter of a named key cache, which creates the cache.
     */
    public function cache(string $cache, Definition $definition, string|int|null $value): void
    {
        if ($cache === 'default') {
            $this->set($definition, $value);

            return;
        }
        $this->caches[$cache][$definition->name] = (int) $value;
    }

    /**
     * Answers the global value of a variable.
     */
    public function value(Definition $definition): string|int|null
    {
        return array_key_exists($definition->name, $this->values) ? $this->values[$definition->name] : $definition->default;
    }

    /**
     * Sets the global value of a variable.
     */
    public function set(Definition $definition, string|int|null $value): void
    {
        $this->values[$definition->name] = $value;
    }
}
