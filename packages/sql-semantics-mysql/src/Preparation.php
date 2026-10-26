<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql;

use SqlParser\Lexer\ParameterSyntax;
use SqlParser\Lexer\SourceException;
use SqlParser\MySql\MySqlParser;
use SqlParser\MySql\SqlMode;
use SqlSemantics\Construction\Origins;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Alternative;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Definition;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation;
use SqlSemantics\Platform\MySql\Statement\Dml\Prepared\SyntaxRefusal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Validation\Equivalence;
use SqlSemantics\Validation\LeafEmbedding;
use SqlSemantics\Validation\Publication;
use SqlSemantics\Validation\ValueGraph;

/**
 * Compiles all lexical interpretations while the SQL input is available.
 *
 * Only grammatical refusal is captured. Missing lowering implementations and
 * constructor failures escape; they cannot masquerade as inactive syntax errors.
 * This input boundary is never invoked by execution or cache invalidation.
 *
 * @visibility public
 * @example Capturing every interpretation of a preparation
 *     $initial = new \SqlSemantics\Platform\MySql\Statement\Dml\Prepared\Interpretation(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::known('utf8mb4'));
 *     (new \SqlSemantics\Platform\MySql\Preparation())->read('SELECT 1', $initial)->initial === $initial // => true
 */
final class Preparation
{
    /**
     * Captures a previously accepted input's complete lexical domain.
     */
    public function read(string $sql, Interpretation $initial, ?Name $database = null): Definition
    {
        $alternatives = [];
        $distinct = [];
        $equivalence = new Equivalence();
        $platform = new Platform();
        $parser = new MySqlParser($platform->profile($initial->language->grammar->value, null, $initial->language->parameters)->grammar->value);
        foreach (Interpretation::all($initial->language->grammar, $initial->language->parameters) as $interpretation) {
            $candidate = $this->alternative($sql, $interpretation, $platform, $parser);
            $shared = null;
            foreach (array_reverse($distinct) as $known) {
                if ($equivalence->difference(['structure' => $known->statement, 'sources' => $known->sources], ['structure' => $candidate->statement, 'sources' => $candidate->sources], true) === null) {
                    $shared = $known;
                    break;
                }
            }
            if ($shared === null) {
                $distinct[] = $candidate;
            }
            $alternatives[] = $shared === null ? $candidate : new Alternative($interpretation, $shared->statement, $shared->sources);
        }
        return new Definition($initial, $database, $alternatives);
    }

    /**
     * Lowers one profile without deriving catalog or execution-time facts.
     */
    public function alternative(string $sql, Interpretation $interpretation, Platform $platform, MySqlParser $parser): Alternative
    {
        $profile = $interpretation->language;
        Check::input($parser->version() === $profile->grammar->value, 'A preparation reader uses the interpretation\'s grammar release.');
        $settings = new Settings($interpretation->client->defaultCollation($profile->grammar), client: $interpretation->client);
        $flags = $profile->lexical;
        $reader = $parser->withSettings(new SqlMode($flags->ansiQuotes, $flags->pipesAsConcat, $flags->highNotPrecedence, $flags->noBackslashEscapes, $flags->ignoreSpace), $profile->parameters === ParameterStyle::Native ? ParameterSyntax::Native : ParameterSyntax::Named, $interpretation->client->name);
        try {
            $tree = $reader->parse($sql);
        } catch (SourceException $error) {
            return new Alternative($interpretation, new SyntaxRefusal($error->offset, $error->position->line, substr($sql, $error->offset, 80)));
        }
        $leaves = new Leaves();
        $origins = new Origins();
        $statement = (new Publication())->root($platform->lower($tree, $profile, $leaves, $origins, $settings));
        $graph = new ValueGraph(['SqlSemantics\\Statement\\', 'SqlSemantics\\Contract\\', $platform->statementNamespace()]);
        $dropped = (new LeafEmbedding())->dropped($leaves, $graph->objects($statement));
        Check::invariant($dropped === null, 'A preparation alternative lost an input operand: ' . ($dropped === null ? '' : $dropped::class));
        return new Alternative($interpretation, $statement, $origins->publish());
    }
}
