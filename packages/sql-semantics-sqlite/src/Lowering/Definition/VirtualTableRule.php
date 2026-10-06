<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Definition;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ModuleArguments;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateVirtualTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\ModuleArgument;
use SqlSemantics\Statement\Statement;

/**
 * Lowers virtual table definitions.
 *
 * Rule: SQLITE-VIRTUAL-TABLE-LOWER-001. Scope: `cmd: create_vtab`,
 * `cmd: create_vtab LP vtabarglist RP`, create_vtab, vtabarglist, vtabarg,
 * vtabargtoken, lp, anylist. Constructors: CreateVirtualTable,
 * ModuleArgument. The grammar accepts any tokens as an argument; each
 * argument lowers to the exact text of its token span
 * (SQLITE-MODULE-ARGUMENT-TEXT-001), so no token of an argument is dropped or
 * respelled. Terminates: the argument subtrees are walked with an explicit
 * stack. Source: https://sqlite.org/lang_createvtab.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class VirtualTableRule
{
    /**
     * The productions an argument list is made of.
     */
    private const ARGUMENT_PRODUCTIONS = [
        'vtabarglist: vtabarg',
        'vtabarglist: vtabarglist COMMA vtabarg',
        'vtabarg:',
        'vtabarg: vtabarg vtabargtoken',
        'vtabargtoken: ANY',
        'vtabargtoken: lp anylist RP',
        'lp: LP',
        'anylist:',
        'anylist: anylist LP anylist RP',
        'anylist: anylist ANY',
    ];

    private readonly CreateTableRule $tables;

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
        $this->tables = new CreateTableRule($lowering);
    }

    /**
     * Lowers a virtual table definition, or answers null for a command of another family.
     */
    public function command(Form $form): ?Statement
    {
        return match ($form->signature) {
            'cmd: create_vtab' => $this->table($form->node(0), null),
            'cmd: create_vtab LP vtabarglist RP' => $this->table($form->node(0), $this->arguments($form->node(2))),
            default => null,
        };
    }

    /**
     * Lowers the head of a virtual table definition and attaches the arguments.
     *
     * @param list<ModuleArgument>|null $arguments
     * @throws ImplementationGap When the production has no rule
     */
    public function table(Node $head, ?array $arguments): CreateVirtualTable
    {
        $form = $this->lowering->productions->form($head);
        if ($form->signature !== 'create_vtab: createkw VIRTUAL TABLE ifnotexists nm dbnm USING nm') {
            throw ImplementationGap::production($form);
        }
        $this->tables->created($form->node(0));
        $ifNotExists = $this->lowering->flags->ifNotExists($form->node(3));
        $name = $this->lowering->names->scoped($form->node(4), $form->node(5));

        return new CreateVirtualTable($name, $this->lowering->names->name($form->node(7)), $arguments, $ifNotExists);
    }

    /**
     * Lowers the argument list to the exact text of each argument, in order.
     *
     * @return list<ModuleArgument>
     * @throws ImplementationGap When a production has no rule
     */
    public function arguments(Node $list): array
    {
        $pending = [$list];
        while ($pending !== []) {
            $current = array_pop($pending);
            if ($current instanceof Token) {
                continue;
            }
            $form = $this->lowering->productions->form($current);
            if (!in_array($form->signature, self::ARGUMENT_PRODUCTIONS, true)) {
                throw ImplementationGap::production($form);
            }
            array_push($pending, ...$current->children);
        }
        $arguments = [];
        foreach ((new ModuleArguments())->texts($list) as $text) {
            $arguments[] = $this->lowering->leaves->record(new ModuleArgument($text));
        }

        return $arguments;
    }
}
