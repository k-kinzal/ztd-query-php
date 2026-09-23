<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Account;

/**
 * A MySQL account or role name, with its username and optional host kept separately.
 * @visibility public
 * @example Identifying a role account
 *     $name = new \SqlSemantics\Model\Configuration\Account\AccountName('reader', 'localhost');
 *     $name->host // => 'localhost'
 */
final class AccountName
{
    /**
     * An omitted host refers to MySQL's percent host; an empty username names an anonymous account.
     */
    public function __construct(public readonly string $username, public readonly ?string $host = null)
    {
    }
}
