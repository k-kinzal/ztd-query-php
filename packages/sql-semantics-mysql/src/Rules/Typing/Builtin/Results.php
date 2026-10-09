<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin;

use Closure;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\ResultTyping;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
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
     * The string functions MySQL 5.6 answers as NULL exactly when an argument can be, by upper-case name, with the index of the count that must be constant, if any (verified on a live 5.6.51 server).
     */
    public const PROPAGATED_56 = [
        'CONCAT' => null, 'CONCAT_WS' => null, 'LOWER' => null, 'LCASE' => null, 'UPPER' => null, 'UCASE' => null, 'QUOTE' => null,
        'REVERSE' => null, 'EXPORT_SET' => null, 'LTRIM' => null, 'RTRIM' => null, 'TRIM' => null, 'REPLACE' => null, 'INSERT' => null,
        'SUBSTRING' => null, 'SUBSTR' => null, 'MID' => null, 'LEFT' => null, 'RIGHT' => null, 'HEX' => null, 'SUBSTRING_INDEX' => null,
        'FORMAT' => null, 'SOUNDEX' => null, 'TO_BASE64' => null, 'REPEAT' => 1, 'LPAD' => 1, 'RPAD' => 1, 'SPACE' => 0,
    ];

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
            (new FormatResults())->rules(),
            (new CountResults())->rules(),
            (new MathResults())->rules(),
            (new Extreme\ExtremeResults())->rules(),
            (new ControlResults())->rules(),
            (new SessionResults())->rules(),
            (new ServerResults())->rules(),
            (new DateResults())->rules(),
            (new SpecialResults())->rules(),
            (new DigestResults())->rules(),
            (new JsonResults())->rules(),
            (new Pattern\PatternResults())->rules(),
        );
    }

    /**
     * Refines the fact of a call with the type its rule resolves, when it resolves one.
     *
     * MySQL 5.6 answers the account functions and PASSWORD as NOT NULL, and most string functions
     * as NULL exactly when an argument can be (verified on a live 5.6.51 server).
     *
     * @param list<Scalar> $arguments The arguments
     * @param list<ScalarFact> $facts The fact of each argument
     */
    public function refine(string $name, array $arguments, array $facts, ScalarFact $fact, Derivation $derivation): ScalarFact
    {
        $type = $this->type($name, $arguments, array_map(static fn (ScalarFact $argument): TypeFact => $argument->type, $facts), $derivation);
        $nullability = $derivation->context->profile->grammar === GrammarRelease::MySql5651 && in_array(strtoupper($name), ['USER', 'SESSION_USER', 'SYSTEM_USER', 'CURRENT_USER', 'PASSWORD'], true) ? Nullability::NotNull : $fact->nullability;

        $nullability = $this->propagated($name, $arguments, $facts, $derivation) ?? $nullability;
        $nullability = (new Pattern\PatternResults())->nullability($name, $type, $nullability);

        return $type === null ? $fact : new ScalarFact(new Known($type), $nullability, $fact->resolution);
    }

    /**
     * Answers the NULL fact MySQL 5.6 gives a string function of PROPAGATED_56, or null for another function or release.
     *
     * The result is NULL exactly when an argument can be; a function whose count is not a literal
     * can also be NULL, as its result could be longer than max_allowed_packet. The server takes any
     * constant count, which a literal approximates here (verified on a live 5.6.51 server).
     *
     * @param list<Scalar> $arguments The arguments
     * @param list<ScalarFact> $facts The fact of each argument
     */
    public function propagated(string $name, array $arguments, array $facts, Derivation $derivation): ?Nullability
    {
        $name = strtoupper($name);
        if ($derivation->context->profile->grammar !== GrammarRelease::MySql5651 || !array_key_exists($name, self::PROPAGATED_56)) {
            return null;
        }
        $count = self::PROPAGATED_56[$name];
        if ($count !== null && !($arguments[$count] ?? null) instanceof NumberLiteral && !($arguments[$count] ?? null) instanceof StringLiteral) {
            return Nullability::Nullable;
        }

        return (new ResultTyping())->nullability('P', $facts);
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
