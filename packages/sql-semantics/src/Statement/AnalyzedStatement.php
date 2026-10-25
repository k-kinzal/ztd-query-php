<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Statement\Declaration\Table;

/**
 * A SQL statement whose semantic values provide its reconstruction.
 *
 * @visibility public
 * @example An analysis result is a concrete SQL statement
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("INSERT t VALUES ('42')");
 *     [$insert instanceof \SqlSemantics\Statement\AnalyzedStatement, $insert->toString()] // => [true, "INSERT t VALUES ('42')"]
 */
interface AnalyzedStatement
{
    /**
     * Reconstructs SQL from the statement's values, without input text or a parser tree.
     */
    public function toString(): string;

    /**
     * Answers the language the statement belongs to.
     */
    public function profile(): LanguageProfile;

    /**
     * Answers the declarations a definition statement contributes to analysis.
     *
     * @return list<Table>
     */
    public function declarations(): array;
}
