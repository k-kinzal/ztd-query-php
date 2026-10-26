<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Platform\MySql\Rendering\Codec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Source\SourceMap;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Validation\ValueGraph;

/**
 * A complete grammatical interpretation, or its syntax refusal, before binding.
 *
 * Parameter occurrences retain their written order and object identity. Their
 * domains and declaration dependencies belong to the subsequently bound value.
 *
 * @visibility public
 * @example A refused interpretation has no parameter operands
 *     $profile = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     (new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($profile, new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal(0, 1, 'FROM')))->parameters // => []
 */
final class Alternative
{
    use Snapshot;

    /**
     * @var list<Operand> The parameter occurrences in written order
     */
    public readonly array $parameters;

    /**
     * Records parameter identities from the typed statement, without SQL parsing.
     */
    public function __construct(public readonly Interpretation $interpretation, public readonly Statement|SyntaxRefusal $statement, public readonly SourceMap $sources = new SourceMap())
    {
        if ($statement instanceof SyntaxRefusal) {
            $this->parameters = [];
            return;
        }
        (new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\MySql\\Statement\\']))->objects($statement, true);
        $output = new Output(new Codec($interpretation->language->grammar, $interpretation->language->lexical));
        $statement->render($output);
        $this->parameters = $output->parameters();
    }
}
