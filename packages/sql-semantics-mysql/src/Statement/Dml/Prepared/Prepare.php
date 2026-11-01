<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * PREPARE: names a statement whose text is given as a string or held by a user variable.
 *
 * Rule: MYSQL-PREPARE-001. The text is SQL the server parses when it runs
 * PREPARE; it is kept as the string it is, because the request is to
 * prepare that text, and a text held by a user variable is a session
 * value. Literal source text is converted to the system character set before
 * its SQL is parsed; the retained operand still renders the original bytes.
 * MySQL 5.6 reports lossy conversion of the complete source string.
 * A user variable is derived as a session value. The statement
 * returns no rows. Terminates: no nested statement is lowered. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/prepare.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a PREPARE
 *     $prepare = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("PREPARE stmt FROM 'SELECT ?'");
 *     [$prepare->name->value, $prepare->source->value, $prepare->toString()] // => ['stmt', 'SELECT ?', "PREPARE stmt FROM 'SELECT ?'"]
 */
final class Prepare extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $name The name of the prepared statement
     * @param Text|UserVariable $source The statement text, or the user variable that holds it
     */
    public function __construct(public readonly Name $name, public readonly Text|UserVariable $source, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input(!$source instanceof Text || $source->radix === null, 'A PREPARE source is a quoted string or a user variable.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the user variable that holds the text, when there is one; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->source instanceof UserVariable) {
            $derivation->scalar($this->source, $derivation->environment());
        } elseif ($derivation->context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $decoded = (new \SqlSemantics\Platform\MySql\Rules\Text\NameEncoding())->decode($this->source->value, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context)->client);
            if (preg_match('/[\\xF0-\\xF4][\\x80-\\xBF]{3}/', $decoded) === 1) {
                $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion(new Name($decoded), true), $this->source);
            }
        }
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('PREPARE')->name($this->name, NameUse::Label)->keyword('FROM')->node($this->source);
    }
}
