<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Replication\Publication;

use SqlSemantics\Model\Validation\Collections;

/**
 * The WITH options of a publication; a null option is not specified and keeps its default or current value.
 * @visibility public
 * @example Reading publication options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE PUBLICATION pub WITH (publish = '', publish_via_partition_root)");
 *     $statement->options->publish // => []
 *     $statement->options->viaPartitionRoot // => true
 *     (new \SqlSemantics\Model\Definition\Replication\Publication\PublicationOptions())->isEmpty() // => true
 */
final class PublicationOptions
{
    /**
     * @param list<PublishedOperation>|null $publish Operations in the publish option; empty publishes none
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(public readonly ?array $publish = null, public readonly ?bool $viaPartitionRoot = null)
    {
        if ($publish !== null) {
            Collections::objects($publish, PublishedOperation::class);
        }
    }

    /**
     * Reports whether no option is specified.
     */
    public function isEmpty(): bool
    {
        return $this->publish === null && $this->viaPartitionRoot === null;
    }
}
