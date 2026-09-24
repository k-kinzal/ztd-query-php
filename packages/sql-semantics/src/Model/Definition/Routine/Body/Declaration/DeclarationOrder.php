<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Body\Declaration;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Checks the declaration order and name uniqueness MySQL requires within one BEGIN ... END block.
 * @visibility SqlSemantics
 */
final class DeclarationOrder
{
    /**
     * Variables and conditions precede cursors, cursors precede handlers, and each variable, condition and cursor name is declared once.
     * @param list<VariableDeclaration|ConditionDeclaration|CursorDeclaration|HandlerDeclaration> $declarations
     * @throws InvalidStructure
     */
    public static function check(array $declarations): void
    {
        $rank = 0;
        $names = [];
        foreach ($declarations as $declaration) {
            $current = match (true) {
                $declaration instanceof CursorDeclaration => 1,
                $declaration instanceof HandlerDeclaration => 2,
                default => 0,
            };
            if ($current < $rank) {
                throw new InvalidStructure('Declare variables and conditions before cursors, and cursors before handlers.');
            }
            $rank = $current;
            foreach (self::names($declaration) as $name) {
                if (isset($names[$name])) {
                    throw new InvalidStructure('A block declares each variable, condition and cursor name once.');
                }
                $names[$name] = true;
            }
        }
    }

    /**
     * Returns the namespace-qualified, case-folded names a declaration introduces.
     * @return list<string>
     */
    public static function names(VariableDeclaration|ConditionDeclaration|CursorDeclaration|HandlerDeclaration $declaration): array
    {
        return match (true) {
            $declaration instanceof VariableDeclaration => array_map(static fn (string $name): string => 'variable:' . strtolower($name), $declaration->names),
            $declaration instanceof ConditionDeclaration => ['condition:' . strtolower($declaration->name)],
            $declaration instanceof CursorDeclaration => ['cursor:' . strtolower($declaration->name)],
            default => [],
        };
    }
}
