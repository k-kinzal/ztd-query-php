<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Lowering\Lists;
use SqlSemantics\Platform\Sqlite\Lowering\Lowering;
use SqlSemantics\Platform\Sqlite\Rules\Identifiers;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Lowers identifier tokens and name lists into decoded names.
 *
 * Rule: SQLITE-NAME-001. Scope: nm, dbnm, fullname, idlist, idlist_opt,
 * collate and the identifier token classes. A name is decoded by
 * SQLITE-IDENTIFIER-DECODE-001 and recorded as an operand leaf; in `nm dbnm`
 * the first name is the schema when the second is present. Terminates: lists
 * are flattened iteratively. Source: https://sqlite.org/lang_keywords.html,
 * https://sqlite.org/lang_naming.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class NameRule
{
    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a name position.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function name(Node $name): Name
    {
        $form = $this->lowering->productions->form($name);

        return match ($form->signature) {
            'nm: idj', 'nm: STRING' => $this->token($form->token(0)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Decodes one identifier token and records the name as an operand leaf.
     */
    public function token(Token $token): Name
    {
        return $this->lowering->leaves->record(new Name((new Identifiers())->decode($token->text)));
    }

    /**
     * Lowers `nm dbnm`: a name that the optional second name turns into its schema.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function scoped(Node $name, Node $second): QualifiedName
    {
        $first = $this->name($name);
        $form = $this->lowering->productions->form($second);

        return match ($form->signature) {
            'dbnm:' => new QualifiedName($first),
            'dbnm: DOT nm' => new QualifiedName($this->name($form->node(1)), $first),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `fullname`: a name with its optional schema.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function qualified(Node $fullname): QualifiedName
    {
        $form = $this->lowering->productions->form($fullname);

        return match ($form->signature) {
            'fullname: nm' => new QualifiedName($this->name($form->node(0))),
            'fullname: nm DOT nm' => $this->pair($form->node(0), $form->node(2)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a schema name and an object name written around a dot.
     */
    public function pair(Node $schema, Node $name): QualifiedName
    {
        $qualifier = $this->name($schema);

        return new QualifiedName($this->name($name), $qualifier);
    }

    /**
     * Lowers an `idlist` into its names in written order.
     *
     * @return list<Name>
     */
    public function list(Node $idlist): array
    {
        $names = [];
        foreach ((new Lists())->items($idlist) as $name) {
            $names[] = $this->name($name);
        }

        return $names;
    }

    /**
     * Lowers an `idlist_opt`: the names in written order, or null when the list is absent.
     *
     * @return list<Name>|null
     * @throws ImplementationGap When the production has no rule
     */
    public function optionalList(Node $list): ?array
    {
        $form = $this->lowering->productions->form($list);

        return match ($form->signature) {
            'idlist_opt:' => null,
            'idlist_opt: LP idlist RP' => $this->list($form->node(1)),
            default => throw ImplementationGap::production($form),
        };
    }

    /**
     * Lowers a `collate`: the collation name, or null when none is written.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function collation(Node $collate): ?Name
    {
        $form = $this->lowering->productions->form($collate);

        return match ($form->signature) {
            'collate:' => null,
            'collate: COLLATE ids' => $this->token($form->token(1)),
            default => throw ImplementationGap::production($form),
        };
    }
}
