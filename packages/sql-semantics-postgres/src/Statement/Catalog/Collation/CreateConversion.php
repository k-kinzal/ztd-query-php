<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Collation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to define a conversion between two character set encodings.
 *
 * Rule: PG-CONVERSION-001. Mirrors `CreateConversionStmt`: whether it is the
 * default conversion, name, source and destination encodings and function.
 * Source: https://www.postgresql.org/docs/17/sql-createconversion.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the encodings of a conversion
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE DEFAULT CONVERSION myconv FOR 'UTF8' TO 'LATIN1' FROM myfunc");
 *     [$operation->statement->isDefault, $operation->statement->source->value, $operation->statement->target->value] // => [true, 'UTF8', 'LATIN1']
 */
final class CreateConversion implements Statement
{
    use Snapshot;

    /**
     * @param bool $isDefault Whether DEFAULT is written
     * @param DottedName $name The conversion name
     * @param StringConstant $source The source encoding
     * @param StringConstant $target The destination encoding
     * @param DottedName $function The conversion function
     */
    public function __construct(
        public readonly bool $isDefault,
        public readonly DottedName $name,
        public readonly StringConstant $source,
        public readonly StringConstant $target,
        public readonly DottedName $function,
    ) {
    }

    /**
     * Derives nothing: conversions and their functions are not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->isDefault) {
            $out->keyword('DEFAULT');
        }
        $out->keyword('CONVERSION')->node($this->name)->keyword('FOR')->node($this->source)->keyword('TO')->node($this->target)->keyword('FROM')->node($this->function);
    }
}
