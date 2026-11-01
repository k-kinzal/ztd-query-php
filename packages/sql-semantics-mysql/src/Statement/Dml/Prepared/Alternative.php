<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Diagnostic\Check;
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
 * @example Refusing a parameter occurrence reused across script members
 *     $profile = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     $parameter = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter();
 *     $statement = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($parameter)]));
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($profile, new \SqlSemantics\Statement\Script([$statement, $statement])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an origin from a different statement
 *     $profile = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     $statement = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter())]));
 *     $sources = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(), 7, 1)]);
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($profile, $statement, $sources) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a spelling notice whose subject is absent from the statement
 *     $profile = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     $statement = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter())]));
 *     $notice = new \SqlSemantics\Statement\Source\SourceNotice(new \SqlSemantics\Statement\Identifier\Name('full'), new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UnquotedFull), 14);
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($profile, $statement, new \SqlSemantics\Statement\Source\SourceMap(notices: [$notice])) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example A syntax refusal cannot retain origins without a statement
 *     $profile = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     $sources = new \SqlSemantics\Statement\Source\SourceMap([new \SqlSemantics\Statement\Source\Origin(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(), 7, 1)]);
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative($profile, new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal(0, 1, 'FROM'), $sources) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
            Check::input($sources->origins === [] && $sources->notices === [], 'A syntax refusal has no statement occurrences to own sources or notices.');
            $this->parameters = [];
            return;
        }
        $graph = new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', 'SqlSemantics\\Platform\\MySql\\Statement\\']);
        $needsOwners = $sources->origins !== [] || $sources->notices !== [];
        $objects = $graph->objects($statement, !$needsOwners);
        $graph->objects($sources, true);
        foreach ($sources->origins as $origin) {
            Check::input(in_array($origin->node, $objects, true), 'A preparation source belongs to an occurrence of its statement.');
        }
        foreach ($sources->notices as $notice) {
            Check::input(in_array($notice->subject, $objects, true), 'A preparation notice belongs to an occurrence of its statement.');
        }
        $output = new Output(new Codec($interpretation->language->grammar, $interpretation->language->lexical));
        $statement->render($output);
        $this->parameters = $output->parameters();
        $seen = [];
        foreach ($this->parameters as $parameter) {
            $identity = spl_object_id($parameter);
            Check::input(!isset($seen[$identity]), 'A parameter occurrence belongs to exactly one written position.');
            $seen[$identity] = true;
        }
    }
}
