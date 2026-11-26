<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Page;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Argument\NativePragmaInteger;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\PragmaScope;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\Pragma;
use SqlSemantics\Platform\Sqlite\Statement\Maintenance\PragmaNumber;

/**
 * Binds a native page operand to the exact pager and interpretation.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class PagePragmaLowering
{
    /**
     * Resolves the native scope and the argument convention of the selected pragma.
     * @throws InvalidConstruction When the qualifier does not identify a known database
     */
    public function lower(BoundStatement $operation, Pragma $statement, PagePragmaKind $kind): PagePragma
    {
        $schema = (new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main');
        $value = $statement->value;
        if ($value === null || $kind === PagePragmaKind::Count) {
            return new PagePragma($kind, $schema);
        }
        $text = $value instanceof PragmaNumber ? $value->nativeText() : $value->value;
        $setting = match ($kind) {
            PagePragmaKind::Size => new RequestedPageSize((new NativePragmaInteger())->parse($text) ?? 0),
            PagePragmaKind::MaximumCount => (new PageLimitArgument())->read($text),
        };

        return new PagePragma($kind, $schema, $setting);
    }
}
