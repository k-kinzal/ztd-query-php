<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Resolves the result type of a call of a built-in function, by the rule of its name.
 *
 * A call resolves only when the function has a rule and every argument resolved; otherwise its
 * fact keeps the class of its type.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Results
{
    /**
     * @var array<string, Closure(Invocation): ?Domain>|null
     */
    private static ?array $rules = null;

    /**
     * Answers the rule of every function, by name.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public static function rules(): array
    {
        return self::$rules ??= array_merge(
            (new TextResults())->rules(),
            (new CountResults())->rules(),
            (new MathResults())->rules(),
            (new ControlResults())->rules(),
            (new SessionResults())->rules(),
            (new DateResults())->rules(),
        );
    }

    /**
     * Refines the fact of a call with the type its rule resolves, when it resolves one.
     *
     * MySQL 5.6 answers the account functions as NOT NULL (verified on a live 5.6.51 server).
     *
     * @param list<Scalar> $arguments The arguments
     * @param list<ScalarFact> $facts The fact of each argument
     */
    public function refine(string $name, array $arguments, array $facts, ScalarFact $fact, Derivation $derivation): ScalarFact
    {
        $type = $this->type($name, $arguments, array_map(static fn (ScalarFact $argument): TypeFact => $argument->type, $facts), $derivation);
        $nullability = $derivation->context->profile->grammar === GrammarRelease::MySql5651 && in_array(strtoupper($name), ['USER', 'SESSION_USER', 'SYSTEM_USER', 'CURRENT_USER'], true) ? Nullability::NotNull : $fact->nullability;

        return $type === null ? $fact : new ScalarFact(new Known($type), $nullability, $fact->resolution);
    }

    /**
     * Resolves the type of a call, or answers null when the function has no rule or an argument has no resolved type.
     *
     * @param list<Scalar> $arguments
     * @param list<TypeFact> $types
     */
    public function type(string $name, array $arguments, array $types, Derivation $derivation): ?Domain
    {
        $rule = self::rules()[strtoupper($name)] ?? null;
        $domains = (new Precision())->all($types);
        if ($rule === null || $domains === null) {
            return null;
        }

        return $rule(new Invocation($domains, $arguments, Settings::of($derivation->context), $derivation));
    }
}
