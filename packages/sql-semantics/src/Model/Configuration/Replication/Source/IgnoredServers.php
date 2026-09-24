<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

use SqlSemantics\Model\Configuration\Replication\ReplicationNumber;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * IGNORE_SERVER_IDS = (...), the server IDs whose events the replica ignores; an empty list clears them.
 * @visibility public
 * @example Reading the server IDs
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('CHANGE REPLICATION SOURCE TO IGNORE_SERVER_IDS = (2, 3)');
 *     array_map(static fn ($id) => $id->text, $statement->settings[0]->servers) // => ['2', '3']
 */
final class IgnoredServers implements SourceSetting
{
    /**
     * @param list<Literal> $servers Server IDs as unsigned number literals, in request order
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $servers)
    {
        Collections::objects($servers, Literal::class);
        foreach ($servers as $server) {
            ReplicationNumber::check($server, 'A server ID');
        }
    }

    /**
     * Names the option the setting assigns.
     */
    public function option(): SourceOption
    {
        return SourceOption::IgnoreServerIds;
    }
}
