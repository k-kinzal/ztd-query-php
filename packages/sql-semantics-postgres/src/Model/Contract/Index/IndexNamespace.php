<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract\Index;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Names and known namespace boundaries for index binding.
 *
 * @visibility SqlSemantics
 */
final class IndexNamespace
{
    use Snapshot;

    /**
     * @var list<Name> Explicitly known schemas
     */
    public readonly array $schemas;

    /**
     * @var list<IndexDeclaration> Declared index identities
     */
    public readonly array $indexes;

    /**
     * @var list<Name> Schemas that may have indexes whose names were not derived
     */
    public readonly array $opaque;

    /**
     * Copies the facts supplied by the catalog definition collector.
     *
     * @param list<Name> $schemas
     * @param list<IndexDeclaration> $indexes
     * @param list<Name> $opaque
     */
    public function __construct(array $schemas = [], array $indexes = [], array $opaque = [])
    {
        $this->schemas = Check::listOf($schemas, Name::class, 'Known schemas must be identifiers.');
        $this->indexes = Check::listOf($indexes, IndexDeclaration::class, 'The index namespace requires index declarations.');
        $this->opaque = Check::listOf($opaque, Name::class, 'Open index schemas must be identifiers.');
    }

    /**
     * Answers whether an explicit schema is known outside the relation list.
     */
    public function known(Name $schema): bool
    {
        return IndexSchemas::stock($schema->value) || in_array($schema->value, array_map(static fn (Name $name): string => $name->value, $this->schemas), true);
    }

    /**
     * Names the namespaces in which inferred absence is unsound.
     */
    public function hasUnknownNames(Name $schema): bool
    {
        return $schema->value === 'pg_toast' || IndexSchemas::sessionInternal($schema->value)
            || in_array($schema->value, array_map(static fn (Name $name): string => $name->value, $this->opaque), true);
    }

}
