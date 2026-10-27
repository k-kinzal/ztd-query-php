<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDeleteForm;
use SqlSemantics\Platform\MySql\Statement\Dml\Target;
use SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintComment;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;

/**
 * A stored deletion retaining a target, input-column or sampling condition and all its dependencies.
 *
 * An unvisited instruction opens no tables. Reaching it resolves its input before
 * reporting the condition; ordinary MultipleDelete values exclude these contradictions.
 *
 * @visibility public
 * @example Keeping a deferred target in a stored definition
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() DELETE q FROM (SELECT 1) q');
 *     $operation->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete // => true
 */
final class DeferredMultipleDelete implements ProgramStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<OptimizerHint> The hints of the comment written right after the verb, in written order
     */
    public readonly array $hints;

    /**
     * @var list<DeleteOption> The modifiers in written order
     */
    public readonly array $options;

    /**
     * @var non-empty-list<QualifiedName> The tables rows are deleted from, in written order
     */
    public readonly array $targets;

    /**
     * @var non-empty-list<Relation> The table references in written order
     */
    public readonly array $tables;

    /**
     * @param WithClause|null $with The common tables (MySQL 8.0 and later)
     * @param list<DeleteOption> $options The modifiers
     * @param Target\DeletionInput $input The coupled target and input namespace
     * @param MultipleDeleteForm $form Where the tables to delete from are written
     * @param Scalar|null $where The row predicate
     * @param list<OptimizerHint> $hints The hints of the comment written right after the verb, in written order
     * @throws InvalidConstruction When a target is repeated or absent from the input namespace, or targets or references are empty
     */
    public function __construct(
        public readonly ?WithClause $with,
        array $options,
        public readonly Target\DeletionInput $input,
        public readonly MultipleDeleteForm $form,
        public readonly ?Scalar $where = null,
        array $hints = [],
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
    ) {
        $this->hints = Check::listOf($hints, OptimizerHint::class, 'The hints of a statement are optimizer hints.');
        $this->options = Check::listOf($options, DeleteOption::class, 'The modifiers of DELETE are delete options.');
        $this->targets = $input->targets;
        $this->tables = $input->tables;
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->readOnly($this->targets, $this->tables, $with) !== null || (new \SqlSemantics\Platform\MySql\Rules\Query\From\InputColumns())->of($this) !== null || (new \SqlSemantics\Platform\MySql\Rules\Dml\Read\Sampling())->of($this) !== null, 'A deferred DELETE retains a target, input-column or sampling contradiction.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the table references, the tables to delete from and WHERE; the statement returns no rows.
     */
    public function deriveProgram(Derivation $derivation, \SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope $scope): void
    {
        (new ChangeFacts())->deleteMultiple($this, $derivation, $scope->environment);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('DELETE');
        $comment = (new HintComment($this->hints))->text();
        if ($comment !== null) {
            $out->comment($comment);
        }
        foreach ($this->options as $option) {
            $out->keyword($option->value);
        }
        if ($this->form === MultipleDeleteForm::Using) {
            $out->keyword('FROM');
        }
        foreach ($this->targets as $position => $target) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($target->schema !== null) {
                $out->name($target->schema, NameUse::Qualifier)->symbol('.');
            }
            $out->name($target->name, NameUse::Relation);
        }
        $out->keyword($this->form === MultipleDeleteForm::Using ? 'USING' : 'FROM')->list($this->tables);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
