<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\Sequence;
use SqlSemantics\Platform\MySql\Rules\Routine\BlockFacts;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Block;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Retains conflicts in a completed declaration list before later malformed input.
 *
 * Each sp_decls production belongs to one block. Ordinary block derivation
 * supplies declaration identity, handler identity and ordering. Only local
 * conflicts are retained: a name resolved through an outer scope cannot be
 * judged from this incomplete block. No tree escapes this input stage.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SyntaxDeclarations
{
    /**
     * Answers the first local declaration conflict of a completed block prefix.
     */
    public function completed(Node $node, Lowering $lowering): ?ParseFailure
    {
        if ($node->name !== 'sp_decls' || $node->children === []) {
            return null;
        }
        $declarations = [];
        try {
            foreach ((new Sequence($lowering))->items($node, ['sp_decls:', 'sp_decls: sp_decls sp_decl ;']) as $item) {
                $declarations[] = (new BlockRule($lowering))->declaration($item);
            }
        } catch (RejectedInput $failure) {
            return new ParseFailure($failure->problem, $failure->aborts);
        }
        $derivation = new Derivation(new AnalysisContext($lowering->profile, [new Name(SessionDatabase::UNNAMED)], complete: false, columnNames: Comparison::AsciiInsensitive));
        (new BlockFacts())->derive(new Block($declarations), $derivation, new ProgramScope($derivation->environment(), ProgramKind::Procedure));
        foreach ($derivation->facts()->diagnostics as $problem) {
            if ($problem instanceof ProgramProblem && in_array($problem->rule, [ProgramRule::DuplicateVariable, ProgramRule::DuplicateCondition, ProgramRule::DuplicateCursor, ProgramRule::DuplicateHandler, ProgramRule::DeclarationAfterCursorOrHandler, ProgramRule::CursorAfterHandler], true)) {
                return new ParseFailure($problem, true);
            }
        }

        return null;
    }
}
