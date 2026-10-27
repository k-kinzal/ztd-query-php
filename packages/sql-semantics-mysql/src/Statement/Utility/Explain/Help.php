<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;

/**
 * HELP: searches the help tables of the server for a topic.
 *
 * Rule: MYSQL-HELP-001. The columns of the result depend on what the search
 * string matches in the help tables: one topic returns its name,
 * description and example, several topics or categories return their names
 * and whether each is a category, a category returns its topics with the
 * category name. The shape is therefore open and depends on the help
 * content of the server. The topic is a string or a name, compared without
 * regard to quoting. Terminates: a leaf.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/help.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the topic
 *     $help = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("HELP 'contents'");
 *     [$help->topic->value, $help->shape()?->complete(), $help->toString()] // => ['contents', false, 'HELP contents']
 */
final class Help extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $topic The search string
     */
    public function __construct(public readonly Name $topic, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records the open shape of the result.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $facts = new ShowFacts();
        $derivation->output($facts->query($facts->open(new SessionState('the help tables of the server'))->shape, $derivation->context->columnNames));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('HELP')->name($this->topic, NameUse::Label);
    }
}
