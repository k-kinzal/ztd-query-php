<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Type\TypeDescriptor;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;

/**
 * Resolves the declared type to an admitted native schema role.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DeclaredDatatypeLowering
{
    /**
     * Decodes an admitted native type after dropping a parsed generation suffix.
     */
    public function lower(ColumnDefinition $column, TypeDescriptor $domain): DeclaredDatatypeName
    {
        $written = $column->type;
        Check::input(
            $written === null || ($written->arguments === [] && (count($written->words) === 1 || $column->typeLayout !== null)),
            'The declared type must be one supported type name.',
        );
        Check::invariant($domain instanceof ColumnDomain, 'A table column has a native declared domain.');
        $type = DeclaredDatatypeName::tryFrom($domain->declared);
        $knownCatalogName = $column->typeLayout !== null && !$domain->strict && $column->generated() !== null
            && $type !== DeclaredDatatypeName::Untyped;
        Check::input(
            $type !== null && ($domain->standard || !$domain->typed() || $knownCatalogName),
            'This declared type has no complete CREATE TABLE representation.',
        );

        return $type;
    }
}
