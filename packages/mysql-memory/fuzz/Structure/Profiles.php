<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use DomainException;
use SqlParser\Lexer\SourceException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Mode;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Validation\Equivalence;
use SqlSemantics\Validation\Publication;

/**
 * Chooses a test environment by structural correspondence, before execution.
 *
 * This is test machinery, not a semantic execution certificate. A model for
 * which no profile corresponds is a failed trial, never a discarded input.
 */
final class Profiles
{
    /**
     * Finds a language interpretation that retains the generated structure.
     *
     * @throws DomainException When no supported interpretation corresponds
     */
    public function select(Statement|Scalar $node): LanguageProfile
    {
        $statement = $node instanceof Statement ? $node : new Select([], [new SelectExpression($node)]);
        $platform = Platforms::of('mysql');
        $failures = [];
        foreach ($this->candidates($statement) as $profile) {
            try {
                $out = new Output($platform->codec($profile));
                $statement->render($out);
                $sql = (new Lexical())->join($out->pieces()) . $out->trailing();
                $tree = $platform->parser($profile)->parse($sql);
                $lowered = (new Publication())->root($platform->lower($tree, $profile, new Leaves()));
                $difference = (new Equivalence())->difference($statement, $lowered);
                if ($difference === null) {
                    return $profile;
                }
                $failure = $difference;
            } catch (SourceException|AnalysisException $error) {
                $failure = $error::class . ': ' . $error->getMessage();
            }
            $failures[] = $profile->grammar->value . '/' . $this->mode($profile) . ': ' . $failure;
        }

        throw new DomainException("No language profile preserves the constructed structure:\n" . implode("\n", $failures));
    }

    /**
     * Enumerates environments independently of database state or execution success.
     *
     * @return iterable<LanguageProfile>
     */
    public function candidates(Node $node): iterable
    {
        $pending = [$node];
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof LanguageProfile) {
                yield $value;

                return;
            } elseif ($value instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }
        $parameters = (new Execution())->parameters($node);
        foreach (GrammarRelease::cases() as $release) {
            if ($release->database() !== 'mysql') {
                continue;
            }
            foreach (['', 'HIGH_NOT_PRECEDENCE', 'PIPES_AS_CONCAT', 'HIGH_NOT_PRECEDENCE,PIPES_AS_CONCAT'] as $mode) {
                yield new LanguageProfile($release, new LexicalSettings($mode), $parameters);
            }
        }
    }

    /**
     * Reconstructs the session's lexical mode from the immutable flags.
     */
    public function mode(LanguageProfile $profile): string
    {
        $settings = $profile->lexical;

        return (new Mode($settings->ansiQuotes, $settings->pipesAsConcat, $settings->highNotPrecedence, $settings->noBackslashEscapes, $settings->ignoreSpace))->toString();
    }
}
