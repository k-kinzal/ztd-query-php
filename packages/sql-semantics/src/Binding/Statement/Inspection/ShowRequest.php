<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Inspection;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;

/**
 * The parsed form of a SHOW request with its leading keywords read once, independent of the grammar release.
 * @visibility SqlSemantics
 */
final class ShowRequest
{
    /**
     * Grammar rules that carry the operands of one SHOW form; releases before 8.0 share a single rule.
     */
    public const FORMS = ['show_param', 'show_databases_stmt', 'show_tables_stmt', 'show_columns_stmt', 'show_keys_stmt', 'show_table_status_stmt', 'show_open_tables_stmt', 'show_triggers_stmt', 'show_events_stmt', 'show_character_set_stmt', 'show_collation_stmt', 'show_create_database_stmt', 'show_create_event_stmt', 'show_create_function_stmt', 'show_create_procedure_stmt', 'show_create_table_stmt', 'show_create_trigger_stmt', 'show_create_user_stmt', 'show_create_view_stmt', 'show_function_status_stmt', 'show_procedure_status_stmt', 'show_function_code_stmt', 'show_procedure_code_stmt', 'show_engine_logs_stmt', 'show_engine_mutex_stmt', 'show_engine_status_stmt', 'show_profile_stmt', 'show_profiles_stmt', 'show_parse_tree_stmt'];

    /**
     * @param Node $form Rule node holding the form's operands
     * @param list<string> $words Uppercased leading keywords after SHOW and its FULL or EXTENDED modifiers
     * @param bool $full Whether FULL was written
     * @param bool $extended Whether EXTENDED was written
     */
    public function __construct(public readonly Node $form, public readonly array $words, public readonly bool $full, public readonly bool $extended)
    {
    }

    /**
     * Reads a SHOW request; null when the statement is not a SHOW form this family classifies by rule.
     */
    public static function of(Node $node): ?self
    {
        if (strtoupper($node->tokens()[0]->text ?? '') !== 'SHOW') {
            return null;
        }
        $form = Tree::outer($node, self::FORMS)[0] ?? null;
        if ($form === null) {
            return null;
        }
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), array_slice($form->tokens(), 0, 6));
        if (($words[0] ?? '') === 'SHOW') {
            array_shift($words);
        }
        $full = false;
        $extended = false;
        while (in_array($words[0] ?? '', ['EXTENDED', 'FULL'], true)) {
            $extended = $extended || $words[0] === 'EXTENDED';
            $full = $full || $words[0] === 'FULL';
            array_shift($words);
        }
        return new self($form, $words, $full, $extended);
    }

    /**
     * Returns the keyword at a position after the modifiers, or an empty string past the end.
     */
    public function word(int $index): string
    {
        return $this->words[$index] ?? '';
    }
}
