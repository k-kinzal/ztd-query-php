<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Reads the resolved types of operands from their type facts.
 *
 * An operand has a resolved type when its fact is a domain, or NULL itself. An expression resolves
 * precisely only when every operand does; otherwise its fact keeps the class of its type.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Precision
{
    /**
     * Answers the resolved type of a fact, or null when the fact holds only the class of its type.
     */
    public function domain(TypeFact $type): ?Domain
    {
        return match (true) {
            $type instanceof Known && $type->descriptor instanceof Domain => $type->descriptor,
            $type instanceof NullOnly => Domain::null(),
            default => null,
        };
    }

    /**
     * Answers the resolved types of every fact, or null when one has none.
     *
     * @param list<TypeFact> $types
     * @return list<Domain>|null
     */
    public function all(array $types): ?array
    {
        $domains = [];
        foreach ($types as $type) {
            $domain = $this->domain($type);
            if ($domain === null) {
                return null;
            }
            $domains[] = $domain;
        }

        return $domains;
    }
}
