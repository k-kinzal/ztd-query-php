<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication;

/**
 * The connection credentials a replication start request can supply, in the order START REPLICA accepts them.
 * @visibility public
 * @example Reading the keyword
 *     \SqlSemantics\Model\Configuration\Replication\CredentialOption::DefaultAuth->value // => 'DEFAULT_AUTH'
 */
enum CredentialOption: string
{
    case User = 'USER';
    case Password = 'PASSWORD';
    case DefaultAuth = 'DEFAULT_AUTH';
    case PluginDir = 'PLUGIN_DIR';
}
