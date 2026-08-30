<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

/**
 * Applies what MySQL's parser enforces outside its grammar.
 *
 * A Bison grammar only says which token sequences the parser will shift and
 * reduce; the C actions attached to those rules reject a good deal more. A
 * derivation that satisfied only the grammar would write SQL the parser
 * refuses, so every such constraint is applied here before the terminals are
 * handed to the lexer.
 */
final class ParserSemantics
{
    /**
     * Applies the constraints MySQL enforces in semantic actions rather than in its grammar.
     *
     * The grammar accepts a qualified name in front of a user variable, a
     * CURRENT_USER() call where the parser wants the bare keyword, an ALTER
     * EVENT with no clause after the name, and a handful of tokens whose
     * spelling depends on what follows them. Each of those is rejected or
     * rewritten by the C actions, so a derivation that satisfied only the
     * grammar would write SQL MySQL refuses to parse.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> Terminals with every semantic constraint satisfied
     */
    public function applied(array $terminals): array
    {
        $terminals = $this->withoutQualifiedUserVariable($terminals);
        $terminals = $this->withoutCurrentUserCall($terminals);
        $terminals = $this->withEventClause($terminals);

        return $this->respelled($terminals);
    }

    /**
     * Drops the qualified name the grammar allows in front of a user variable.
     *
     * The grammar will derive `db.tbl.@name`, but the action that builds the
     * variable takes the name alone, so the qualification in front of it is
     * dropped rather than written out.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> The terminals with any such qualification removed
     */
    public function withoutQualifiedUserVariable(array $terminals): array
    {
        $remove = [];
        foreach ($terminals as $index => $terminal) {
            if ($terminal !== '@') {
                continue;
            }
            $dot = $index - 2;
            foreach ($terminals as $ignored) {
                if ($dot < 1 || $terminals[$dot] !== '.') {
                    break;
                }
                $remove[$dot] = true;
                $remove[$dot - 1] = true;
                $dot -= 2;
            }
        }
        if ($remove === []) {
            return $terminals;
        }

        return array_values(array_diff_key($terminals, $remove));
    }

    /**
     * Drops the empty argument list after CURRENT_USER where the parser wants the bare keyword.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> The terminals with any such call written as the keyword alone
     */
    public function withoutCurrentUserCall(array $terminals): array
    {
        foreach ($terminals as $index => $terminal) {
            if (in_array($terminal, ['CURRENT_USER', 'CURRENT_USER_SYM'], true)
                && ($terminals[$index + 1] ?? null) === '('
                && ($terminals[$index + 2] ?? null) === ')'
                && ($terminals[$index + 3] ?? null) === ':'
            ) {
                array_splice($terminals, $index + 1, 2);
            }
        }

        return $terminals;
    }

    /**
     * Gives an ALTER EVENT the clause the action insists it carries.
     *
     * The grammar will derive an ALTER EVENT that names an event and stops
     * there; the action refuses it, so a clause is written after the name.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> The terminals, with a clause where the statement had none
     */
    public function withEventClause(array $terminals): array
    {
        $event = array_search('EVENT_SYM', $terminals, true);
        $alter = array_search('ALTER_SYM', $terminals, true);
        if ($event === false || $alter === false) {
            return $terminals;
        }

        $afterName = $event + 2;
        if (($terminals[$afterName] ?? null) === '.' && isset($terminals[$afterName + 1])) {
            $afterName += 2;
        }
        if ($afterName >= count($terminals)) {
            $terminals[] = 'ENABLE_SYM';
        }

        return $terminals;
    }

    /**
     * Respells the tokens whose spelling depends on what sits beside them.
     *
     * MySQL writes `=` as a different token in front of a quantifier, drops
     * RELEASE after CHAIN unless NO stands before it, and reads a number after
     * a colon or SYSTEM as a plain one.
     *
     * @param list<string> $terminals Terminals a derivation produced
     *
     * @return list<string> The terminals, spelled as the parser reads them
     */
    public function respelled(array $terminals): array
    {
        $result = [];
        foreach ($terminals as $index => $terminal) {
            $previous = $result[count($result) - 1] ?? null;
            if ($terminal === 'EQUAL_SYM'
                && in_array($terminals[$index + 1] ?? null, ['ALL', 'ALL_SYM', 'ANY', 'ANY_SYM', 'SOME', 'SOME_SYM'], true)
            ) {
                $terminal = 'EQ';
            }
            if (in_array($terminal, ['RELEASE', 'RELEASE_SYM'], true)
                && in_array($previous, ['CHAIN', 'CHAIN_SYM'], true)
                && !in_array($result[count($result) - 2] ?? null, ['NO', 'NO_SYM'], true)
            ) {
                continue;
            }
            if (in_array($terminal, ['DECIMAL_NUM', 'FLOAT_NUM'], true)
                && ($previous === ':' || in_array($previous, ['SYSTEM', 'SYSTEM_SYM'], true))
            ) {
                $terminal = 'NUM';
            }
            $result[] = $terminal;
        }

        return $result;
    }
}
