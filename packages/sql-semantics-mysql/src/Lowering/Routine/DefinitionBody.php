<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine;

use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyStructure;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\RefusedProgramBody;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Platform\MySql\Statement\Type\DeclaredName;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * Reports declaration-kind contradictions before a public statement is constructed.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class DefinitionBody
{
    /**
     * Interprets the language/body relationship and retains only its refusal location.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Characteristic> $characteristics The written characteristics
     */
    public function language(Node $body, array $characteristics, \SqlParser\Parser\Node $tree): ?\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch
    {
        if ((new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyLanguage())->compatible($body, $characteristics)) {
            return null;
        }
        $tokens = $tree->tokens();
        $offset = null;
        if ($body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody) {
            $offset = $tokens[1]->offset;
        } elseif ($body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Block) {
            $offset = $tokens[count($tokens) - 1]->offset;
        }
        return new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch($offset);
    }

    /**
     * Preserves the typed header when a completed body contradicts its declaration kind.
     *
     * @param list<int> $typeEnds The completion offsets for preceding parameter and return declarations
     * @throws RejectedInput When no declaration can contain the body
     */
    public function check(Node $body, ProgramKind $kind, QualifiedName $name, ?Account $definer, int $end, ?ParameterList $parameters = null, ?DeclaredName $returns = null, ?CollationName $collation = null, array $typeEnds = [], int $nameEnd = 0, ?\SqlSemantics\Platform\MySql\Statement\Routine\Problem\LanguageBodyMismatch $language = null): void
    {
        $problem = (new BodyStructure())->problem($body, $kind, $name);
        if ($language !== null && ($problem === null || in_array($problem->rule, [\SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::MissingReturn, \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::ResultSet, \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::CommitInFunction, \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule::FunctionStatement], true))) {
            $problem = $language;
        }
        $problem ??= (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyBindings())->problem($body, $kind);
        $problem ??= (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyArrays())->problem($body);
        if ($problem !== null) {
            throw new RejectedInput(new RefusedProgramBody($name, $kind, $problem, $definer, $parameters, $returns, $collation, $typeEnds, $nameEnd), $end, false);
        }
    }

    /**
     * Orders parameter completions and the optional return declaration without retaining their trees.
     *
     * @return list<int>
     */
    public function typeEnds(\SqlParser\Parser\Node $parameters, ?\SqlParser\Parser\Node $returns = null): array
    {
        $ranges = new \SqlSemantics\Construction\SourceRanges();
        $pending = [$parameters];
        $ends = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (in_array($node->name, ['sp_fdparam', 'sp_pdparam'], true)) {
                $ends[] = $ranges->span($node)[1] ?? 0;
                continue;
            }
            foreach (array_reverse($node->children) as $child) {
                if ($child instanceof \SqlParser\Parser\Node) {
                    $pending[] = $child;
                }
            }
        }
        if ($returns !== null) {
            $ends[] = $ranges->span($returns)[1] ?? 0;
        }
        return $ends;
    }
}
