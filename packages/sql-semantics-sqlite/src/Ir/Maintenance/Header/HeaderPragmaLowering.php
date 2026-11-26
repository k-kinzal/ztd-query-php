<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Argument\NativePragmaInteger;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

/**
 * Resolves a header pragma's database and normalized native write value.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class HeaderPragmaLowering
{
    /**
     * Resolves the physical schema and the field's read or write role.
     * @throws InvalidConstruction When the qualifier does not identify a known database
     */
    public function lower(BoundStatement $operation, Pragma $statement, HeaderCookie|HeaderReadField $field): HeaderPragma
    {
        $schema = (new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main');
        $value = $statement->value;
        if ($value === null || $field instanceof HeaderReadField) {
            return new HeaderPragma($field, $schema);
        }
        $text = $value instanceof PragmaNumber ? $value->nativeText() : $value->value;
        $integer = (new NativePragmaInteger())->parse($text) ?? 0;

        return new HeaderPragma($field, $schema, new HeaderInteger($integer));
    }
}
