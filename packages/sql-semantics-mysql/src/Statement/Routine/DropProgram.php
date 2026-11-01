<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * DROP PROCEDURE, DROP FUNCTION, DROP TRIGGER or DROP EVENT.
 *
 * Rule: MYSQL-DROP-PROGRAM-001. The statement is structured as a request
 * only: it changes no context and derives nothing. DROP FUNCTION with an
 * unqualified name drops a stored function of the current database or,
 * when there is none, a loadable function of that name; which one is not
 * decided here.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-function-loadable.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-trigger.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-event.html.
 * Status: Implemented.
 *
 * @example Refusing an empty procedure name before derivation
 *     new \SqlSemantics\Platform\MySql\Statement\Routine\DropProgram(\SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Procedure, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('')))  // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @visibility public
 * @example Reading a DROP TRIGGER
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DROP TRIGGER IF EXISTS shop.tr');
 *     [$drop->kind->value, $drop->ifExists, $drop->name->schema->value] // => ['TRIGGER', true, 'shop']
 */
final class DropProgram extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ProgramKind $kind The kind of stored program dropped
     * @param QualifiedName $name The program name with its optional database
     * @param bool $ifExists Whether IF EXISTS is written
     */
    public function __construct(public readonly ProgramKind $kind, public readonly QualifiedName $name, public readonly bool $ifExists = false, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input(!str_contains($name->name->value, "\0"), 'A dropped program name cannot contain NUL.');
        if ($kind !== ProgramKind::Function) {
            ProgramNames::check($name);
        }
        Check::input($name->catalog === null, 'A program name has at most a database qualifier.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Tells whether the name needs MySQL 5.6's unrestricted DROP FUNCTION operand.
     */
    public function legacyName(): bool
    {
        return $this->kind === ProgramKind::Function && ProgramNames::problem($this->name->name) !== null;
    }

    /**
     * Derives nothing: dropping a stored program is a request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', $this->kind->value);
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        (new ProgramNames())->qualified($out, $this->name);
    }
}
