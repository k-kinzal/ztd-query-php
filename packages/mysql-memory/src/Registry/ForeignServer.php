<?php

declare(strict_types=1);

namespace MySqlMemory\Registry;

/**
 * A foreign server CREATE SERVER defines, as a row of mysql.servers holds it.
 *
 * An option the statement does not give is empty, and PORT is 0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-server.html.
 *
 * @visibility MySqlMemory
 */
final class ForeignServer
{
    /**
     * The name stored in mysql.servers, bounded to its 64-character field.
     */
    public readonly string $name;

    /**
     * The lookup name, initially padded after its first NUL; null when a storage reload cannot index that name.
     */
    public ?string $lookupName;

    /**
     * @var array<string, string|int> The decoded options before storage, refreshed from stored fields by ALTER
     */
    public array $cachedOptions;

    /**
     * @param string $name The server name, as the statement that created it wrote it
     * @param string $wrapper The FOREIGN DATA WRAPPER name
     * @param array<string, string|int> $options The value of each option, by lower-case option name: host, database, user, password, socket, owner and port
     * @param bool $legacy Whether MySQL 5.6 stores the padded lookup bytes
     * @param array<string, string|int>|null $cachedOptions The initial decoded options before field conversion
     */
    public function __construct(string $name, public readonly string $wrapper, public array $options = [], bool $legacy = false, ?array $cachedOptions = null)
    {
        $this->lookupName = str_pad(explode("\0", $name, 2)[0], strlen($name), "\0");
        $this->name = rtrim(mb_substr($legacy ? $this->lookupName : $name, 0, 64, 'UTF-8'), ' ');
        $this->cachedOptions = $cachedOptions ?? $options;
    }
}
