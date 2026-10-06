<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Server\Instance;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\AlterServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\DropServer;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption;
use SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind;
use SqlSemantics\Statement\Statement;

/**
 * Lowers CREATE, ALTER and DROP SERVER.
 *
 * Rule: MYSQL-FOREIGN-SERVER-001. Scope: the SERVER alternatives of create,
 * alter and drop (alter and drop in 5.6 and 5.7), server_def (5.6),
 * alter_server_stmt, drop_server_stmt, server_options_list, server_option.
 * Constructs: CreateServer, AlterServer, DropServer, ServerOption.
 * Terminates: the option list is flattened iteratively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-server.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-server.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-server.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Server
 */
final class ForeignServerRule
{
    /**
     * The option productions, by the option they set.
     */
    private const OPTIONS = [
        'server_option: USER TEXT_STRING_sys' => ServerOptionKind::User, 'server_option: HOST_SYM TEXT_STRING_sys' => ServerOptionKind::Host,
        'server_option: DATABASE TEXT_STRING_sys' => ServerOptionKind::Database, 'server_option: OWNER_SYM TEXT_STRING_sys' => ServerOptionKind::Owner,
        'server_option: PASSWORD TEXT_STRING_sys' => ServerOptionKind::Password, 'server_option: SOCKET_SYM TEXT_STRING_sys' => ServerOptionKind::Socket,
        'server_option: PORT_SYM ulong_num' => ServerOptionKind::Port,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a server statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function statement(Form $form): Statement
    {
        $names = $this->lowering->names;

        return match ($form->signature) {
            'create: CREATE server_def' => $this->statement($this->lowering->form($form->node(1))),
            'server_def: SERVER_SYM ident_or_text FOREIGN DATA_SYM WRAPPER_SYM ident_or_text OPTIONS_SYM ( server_options_list )' => $this->create($form, 1),
            'create: CREATE SERVER_SYM ident_or_text FOREIGN DATA_SYM WRAPPER_SYM ident_or_text OPTIONS_SYM ( server_options_list )' => $this->create($form, 2),
            'alter: ALTER SERVER_SYM ident_or_text OPTIONS_SYM ( server_options_list )', 'alter_server_stmt: ALTER SERVER_SYM ident_or_text OPTIONS_SYM ( server_options_list )' => new AlterServer(
                $names->identifier($form->node(2)),
                $this->options($form->node(5)),
            ),
            'drop: DROP SERVER_SYM if_exists ident_or_text', 'drop_server_stmt: DROP SERVER_SYM if_exists ident_or_text' => new DropServer(
                $this->lowering->options->present($form->node(2)),
                $names->identifier($form->node(3)),
            ),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers CREATE SERVER from the position of its name.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function create(Form $form, int $name): CreateServer
    {
        $names = $this->lowering->names;

        return new CreateServer($names->identifier($form->node($name)), $names->identifier($form->node($name + 4)), $this->options($form->node($name + 7)));
    }

    /**
     * Lowers the option list: a node of `server_options_list`.
     *
     * @return list<ServerOption>
     * @throws ImplementationGap When a production has no rule
     */
    public function options(Node $list): array
    {
        $this->lowering->names->claimed($this->lowering->form($list), ['server_options_list: server_option', 'server_options_list: server_options_list , server_option']);
        $options = [];
        foreach ((new Lists())->items($list) as $item) {
            $form = $this->lowering->form($item);
            $kind = self::OPTIONS[$form->signature] ?? throw ImplementationGap::production($form);
            $options[] = new ServerOption($kind, $kind === ServerOptionKind::Port ? $this->lowering->numbers->numeral($form->node(1)) : $this->lowering->literals->text($form->node(1)));
        }

        return $options;
    }
}
