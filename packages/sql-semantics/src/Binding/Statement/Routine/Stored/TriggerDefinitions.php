<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Stored;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\MySqlNames;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Query\TableOccurrence;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramBinder;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramFrame;
use SqlSemantics\Binding\Statement\Routine\Program\ProgramNamespace;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Stored\ProgramKind;
use SqlSemantics\Model\Definition\Routine\Stored\TriggerOrder;
use SqlSemantics\Model\Definition\Routine\Stored\TriggerOrdering;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Relation\TriggerRow;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateTriggerStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Trigger\RowVersion;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Trigger\WriteEvent;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Binds MySQL CREATE TRIGGER; the body sees the OLD and NEW rows its event supplies.
 * @visibility SqlSemantics
 */
final class TriggerDefinitions
{
    /**
     * A trigger and its table named in explicit databases must name the same one.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     * @throws InvalidStructure
     */
    public static function bind(Origin $origin, Node $tail, QueryContext $context, AccountName|CurrentAccount|null $definer): CreateTriggerStatement
    {
        $identifiers = $context->tables->identifiers;
        $name = StoredPrograms::name(Tree::child($tail, ['sp_name']), $context);
        $subject = Tree::child($tail, ['table_ident']) ?? throw new UnclassifiedSql('A trigger requires its table.');
        $tableParts = $identifiers->parts($subject);
        if (count($name->parts) === 2 && count($tableParts) === 2 && !$identifiers->relationEqual($name->parts[0], $tableParts[0])) {
            throw new InvalidSql(InputViolation::ProgramDefinition, $subject);
        }
        $table = TableOccurrence::resolve($subject, $context, $origin->scopeId);
        $table = $table instanceof TableReference ? $table : throw new UnclassifiedSql('A MySQL trigger names one table.');
        $timing = Timing::from(strtoupper(Tree::child($tail, ['trg_action_time'])?->tokens()[0]->text ?? ''));
        $event = WriteEvent::from(strtoupper(Tree::child($tail, ['trg_event'])?->tokens()[0]->text ?? ''));
        $versions = match ($event) {
            WriteEvent::Insert => [RowVersion::New],
            WriteEvent::Delete => [RowVersion::Old],
            WriteEvent::Update => [RowVersion::Old, RowVersion::New],
        };
        $rows = [];
        foreach ($versions as $version) {
            $rows[$version->value] = new TriggerRow($context->ids->relation(), $origin->scopeId, $table->declaration, $table->source, $version);
        }
        $clause = Tree::child($tail, ['trigger_follows_precedes_clause']);
        $order = null;
        if ($clause !== null) {
            $anchor = Tree::child($clause, ['ident_or_text']) ?? throw new UnclassifiedSql('A trigger order requires the other trigger.');
            $order = new TriggerOrder(TriggerOrdering::from(strtoupper($clause->tokens()[0]->text)), MySqlNames::read($anchor->tokens()[0], $identifiers));
        }
        $frame = ProgramFrame::start(ProgramKind::Trigger, $context, new ProgramNamespace([], true, $rows), $timing);
        $body = ProgramBinder::statement(Tree::child($tail, ['sp_proc_stmt']) ?? throw new UnclassifiedSql('A trigger requires its body.'), $frame);
        return new CreateTriggerStatement($origin, $name, $timing, $event, $table, $body, $order, $definer, Tree::child($tail, ['opt_if_not_exists']) !== null);
    }
}
