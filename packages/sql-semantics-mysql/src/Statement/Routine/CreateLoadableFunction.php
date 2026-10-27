<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * CREATE [AGGREGATE] FUNCTION ... SONAME: registers a loadable function of a shared library.
 *
 * Rule: MYSQL-LOADABLE-FUNCTION-001. The statement is structured as a
 * request only: it changes no context and derives nothing. A loadable
 * function has no database and no definer.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-function-loadable.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a loadable function
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE AGGREGATE FUNCTION median RETURNS REAL SONAME 'udf.so'");
 *     [$create->aggregate, $create->returns->value, $create->library->value] // => [true, 'REAL', 'udf.so']
 */
final class CreateLoadableFunction extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $name The function name
     * @param LoadableResult $returns The return type
     * @param Text $library The file name of the shared library, written as a quoted string
     * @param bool $aggregate Whether the function is an aggregate function
     * @param bool $ifNotExists Whether IF NOT EXISTS is written (MySQL 8.0.29 and later)
     */
    public function __construct(
        public readonly Name $name,
        public readonly LoadableResult $returns,
        public readonly Text $library,
        public readonly bool $aggregate = false,
        public readonly bool $ifNotExists = false,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input($library->radix === null, 'A library name is written as a quoted string.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: registering a loadable function is a request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Tells whether the name collides with a keyword or built-in function in this release.
     *
     * A loadable function reports this collision before examining the library path.
     * Observed with quoted and unquoted names on native MySQL 5.6 through 9.1.
     */
    public function nameCollision(\SqlSemantics\Contract\GrammarRelease $release): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rendering\Keywords($release))->contains($this->name->value)
            || (new \SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions())->exists($release, $this->name->value);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->aggregate) {
            $out->keyword('AGGREGATE');
        }
        $out->keyword('FUNCTION');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->name($this->name, NameUse::Routine)->keyword('RETURNS', $this->returns->value, 'SONAME')->node($this->library);
    }
}
