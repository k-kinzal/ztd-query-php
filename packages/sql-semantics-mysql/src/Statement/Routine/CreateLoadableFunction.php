<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;

/**
 * CREATE [AGGREGATE] FUNCTION ... SONAME: registers a loadable function of a shared library.
 *
 * Rule: MYSQL-LOADABLE-FUNCTION-001. The statement is structured as a
 * request only: it changes no context and derives nothing. A loadable
 * function has no database and no definer.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-function-loadable.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateLoadableFunction implements Statement
{
    use Snapshot;

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
    ) {
        Check::input($library->radix === null, 'A library name is written as a quoted string.');
    }

    /**
     * Derives nothing: registering a loadable function is a request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
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
