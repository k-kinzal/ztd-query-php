<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Leaf;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Lowers character set and collation names.
 *
 * Rule: MYSQL-CHARSET-NAME-001. Scope: charset_name,
 * charset_name_or_default, old_or_new_charset_name,
 * old_or_new_charset_name_or_default, default_charset, collation_name,
 * collation_name_or_default, opt_collate, opt_collate_explicit,
 * default_collation. A name is an identifier or a string; the keyword
 * BINARY at such a position is the name `binary`; the keyword DEFAULT is the
 * absence of a name. The optional DEFAULT before CHARACTER SET or COLLATE
 * and the optional equals sign of a table or database option are not kept.
 * Constructs: Name, CharsetName, CollationName. Terminates: unit
 * productions over strict subtrees.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-syntax.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CharsetRule
{
    /**
     * The unit productions that pass a name on to their only child.
     */
    private const FORWARD = [
        'charset_name_or_default: charset_name' => true, 'old_or_new_charset_name_or_default: old_or_new_charset_name' => true,
        'collation_name_or_default: collation_name' => true,
    ];

    /**
     * The productions that hold a name as an identifier or string.
     */
    private const NAMED = ['charset_name: ident_or_text' => true, 'old_or_new_charset_name: ident_or_text' => true, 'collation_name: ident_or_text' => true];

    /**
     * The productions that write the keyword BINARY for the name `binary`.
     */
    private const BINARY = [
        'charset_name: BINARY' => true, 'charset_name: BINARY_SYM' => true, 'old_or_new_charset_name: BINARY' => true,
        'old_or_new_charset_name: BINARY_SYM' => true, 'collation_name: BINARY_SYM' => true,
    ];

    /**
     * The productions that write the keyword DEFAULT in place of a name.
     */
    private const UNNAMED = [
        'charset_name_or_default: DEFAULT' => true, 'old_or_new_charset_name_or_default: DEFAULT' => true,
        'old_or_new_charset_name_or_default: DEFAULT_SYM' => true, 'collation_name_or_default: DEFAULT' => true,
    ];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers a character set or collation name, or answers null for the keyword DEFAULT.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function name(Node $name): ?Name
    {
        $form = $this->lowering->productions->form($name);
        if (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->productions->form($form->node(0));
        }
        if (isset(self::NAMED[$form->signature])) {
            return $this->lowering->names->identifier($form->node(0));
        }
        if (isset(self::BINARY[$form->signature])) {
            return $this->lowering->leaves->record(new Name('binary'));
        }

        return isset(self::UNNAMED[$form->signature]) ? null : throw ImplementationGap::production($form);
    }

    /**
     * Lowers a character set position.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function charset(Node $charset): CharsetName
    {
        $form = $this->lowering->productions->form($charset);

        return $this->lowering->leaves->record(new CharsetName($this->name(match ($form->signature) {
            'default_charset: opt_default charset opt_equal charset_name_or_default', 'default_charset: opt_default character_set opt_equal charset_name' => $form->node(3),
            default => $charset,
        })));
    }

    /**
     * Lowers a collation position; an absent COLLATE clause is null.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function collation(Node $collation): ?CollationName
    {
        $form = $this->lowering->productions->form($collation);
        $name = match ($form->signature) {
            'opt_collate:', 'opt_collate_explicit:' => null,
            'opt_collate: COLLATE_SYM collation_name_or_default', 'opt_collate: COLLATE_SYM collation_name', 'opt_collate_explicit: COLLATE_SYM collation_name' => $form->node(1),
            'default_collation: opt_default COLLATE_SYM opt_equal collation_name_or_default', 'default_collation: opt_default COLLATE_SYM opt_equal collation_name' => $form->node(3),
            default => $collation,
        };

        return $name === null ? null : $this->lowering->leaves->record(new CollationName($this->name($name)));
    }
}
