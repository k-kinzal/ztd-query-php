<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Expression\Access;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Operands;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A JSON path extraction from a column: `col->'path'`, which is `JSON_EXTRACT(col, 'path')`, or `col->>'path'`, which is `JSON_UNQUOTE(JSON_EXTRACT(col, 'path'))` (MySQL 5.7 and later).
 *
 * Rule: MYSQL-JSON-EXTRACTION-001. Facts: `->` yields a JSON value and
 * `->>` a LONGTEXT; both are NULL when the path selects nothing, so they
 * can always be NULL. Terminates: the column is a strict part.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#operator_json-column-path,
 * https://dev.mysql.com/doc/refman/8.4/en/json-search-functions.html#operator_json-inline-path.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the path of an unquoting extraction
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT a FROM t WHERE a->>'$.b' = 'x'");
 *     [$query->statement->where->left->path->value, $query->statement->where->left->unquote] // => ['$.b', true]
 */
final class JsonExtraction implements Scalar
{
    use Snapshot;

    /**
     * @param ColumnUse $column The JSON column
     * @param Text $path The JSON path
     * @param bool $unquote Whether the result is unquoted (`->>`)
     */
    public function __construct(public readonly ColumnUse $column, public readonly Text $path, public readonly bool $unquote = false)
    {
        Check::input($path->radix === null, 'A JSON path is a quoted string.');
    }

    /**
     * Derives the column; the result is JSON or text.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        (new Operands())->single($derivation->scalar($this->column, $environment), $derivation);

        return new ScalarFact(new Known($this->unquote ? new Character(CharacterKind::LongText) : new Elementary(ElementaryKind::Json)), Nullability::Nullable);
    }

    /**
     * Writes the column, the operator and the path.
     */
    public function render(Output $out): void
    {
        $out->node($this->column)->symbol($this->unquote ? '->>' : '->')->node($this->path);
    }
}
