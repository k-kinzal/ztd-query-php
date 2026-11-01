<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions;
use SqlSemantics\Platform\MySql\Rules\Call\RoutineCalls;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Results;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecation;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

/**
 * A call written as `name(...)` or `db.name(...)`: a native function reached by name, a loadable function or a stored function.
 *
 * The server mirrors this as PTI_function_call_generic_ident_sys and
 * PTI_function_call_generic_2d. Only the unqualified form takes argument
 * aliases. The facts follow MYSQL-ROUTINE-CALL-001. The name is written
 * against its parenthesis, except for explicitly bare unqualified names.
 * Those keep a separator so COUNT (1), for example, remains a generic call
 * rather than becoming the COUNT aggregate.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html.
 * Status: Implemented.
 *
 * @example Refusing an empty contextualized routine name
 *     new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a trailing space in a contextualized routine name
 *     new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('f ')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an overlong contextualized routine name
 *     new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name(str_repeat('f', 65))) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @visibility public
 * @example Typing a call of a native function reached by name
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("SELECT concat('a', 'b')");
 *     $query->field(0)->type->descriptor->name() // => 'VARCHAR'
 * @example Depending on the signature of a stored function
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT db.score(1)');
 *     $query->field(0)->type->missing[0]->describe() // => 'the signature of routine score'
 * @example Refusing an alias on a qualified call
 *     new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('f'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Statement\Identifier\Name('x'))], new \SqlSemantics\Statement\Identifier\Name('db')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing an argument count that no supported native ABS accepts
 *     new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('ABS')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing argument aliases for a function that is always native
 *     new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('ABS'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Statement\Identifier\Name('n'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FunctionCall implements Scalar
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<CallArgument> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @param Name $name The function name
     * @param list<CallArgument> $arguments The arguments in order
     * @param Name|null $schema The database of a qualified call
     */
    public function __construct(public readonly Name $name, array $arguments = [], public readonly ?Name $schema = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        \SqlSemantics\Platform\MySql\Rules\Routine\ProgramNames::check(new \SqlSemantics\Statement\Identifier\QualifiedName($name, $schema));
        $this->arguments = Check::listOf($arguments, CallArgument::class, 'Function arguments are call arguments.');
        Check::input($schema === null || !$this->named(), 'Only an unqualified call takes argument aliases.');
        Check::input($schema !== null || (new NativeFunctions())->possible($name, count($this->arguments), $this->named()), 'A generic call must have a supported native signature or a stored-function interpretation.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Tells whether an argument has an alias.
     */
    public function named(): bool
    {
        foreach ($this->arguments as $argument) {
            if ($argument->alias !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derives the arguments and the result of the called function.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        if ($this->schema === null && strcasecmp($this->name->value, 'FOUND_ROWS') === 0) {
            Deprecation::raise(Deprecated::FoundRows, $derivation);
        }
        $legacy = $this->schema === null ? match (strtoupper($this->name->value)) {
            'DES_ENCRYPT' => Deprecated::DesEncrypt,
            'DES_DECRYPT' => Deprecated::DesDecrypt,
            'ENCRYPT' => Deprecated::Encrypt,
            'MASTER_POS_WAIT' => Deprecated::MasterPosWait,
            'WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS' => Deprecated::WaitUntilSqlThreadAfterGtids,
            'JSON_MERGE' => Deprecated::JsonMerge,
            default => null,
        } : null;
        if ($legacy !== null) {
            Deprecation::raise($legacy, $derivation);
        }
        $invalid = (new \SqlSemantics\Platform\MySql\Rules\Call\NamedConstant())->check($this, $derivation);
        $invalid ??= $derivation->context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? null : (new RoutineCalls())->signature($this, $derivation);
        if ($this->schema === null && $invalid === null) {
            Deprecation::spatial($this->name->value, $derivation);
        }
        $facts = [];
        foreach ($this->arguments as $argument) {
            $facts[] = (new Arguments())->one($argument->expression, $derivation, $environment);
        }

        $fact = $invalid ?? (new RoutineCalls())->result($this, $facts, $derivation);
        if ($fact->type instanceof \SqlSemantics\Statement\Type\Invalid || !$this->native($derivation->context->profile->grammar)) {
            return $fact;
        }

        return (new Results())->refine($this->name->value, array_map(static fn ($argument): Scalar => $argument->expression, $this->arguments), $facts, $fact, $derivation);
    }

    /**
     * Writes the call with the name against its parenthesis.
     */
    public function render(Output $out): void
    {
        if ($this->schema !== null) {
            $out->name($this->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name, NameUse::Routine);
        if ($this->schema === null && $this->name->quote === \SqlSemantics\Statement\Identifier\NameQuote::Bare) {
            $out->separate();
        } else {
            $out->glue();
        }
        $out->symbol('(')->list($this->arguments)->symbol(')');
    }

    /**
     * Tells whether a generic call names a keyword of its release.
     * An absent stored function then has the keyword-collision diagnostic; the name
     * remains valid for an existing stored function and is not rejected by construction.
     *
     * @example Distinguishing a keyword name from an ordinary function name
     *     (new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('COLLATION')))->keywordName(\SqlSemantics\Contract\GrammarRelease::MySql847) // => true
     */
    public function keywordName(\SqlSemantics\Contract\GrammarRelease $release): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rendering\Keywords($release))->contains($this->name->value);
    }

    /**
     * Tells whether generic-call resolution selects a native function before stored routines.
     * Keyword-call syntax has a separate type; a generic call with that keyword name
     * still resolves as a stored function. A database qualifier always selects a routine.
     *
     * @example Resolving an ordinary native function
     *     (new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('ABS'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral())]))->native(\SqlSemantics\Contract\GrammarRelease::MySql847) // => true
     */
    public function native(\SqlSemantics\Contract\GrammarRelease $release): bool
    {
        return $this->schema === null && (new NativeFunctions())->exists($release, $this->name->value);
    }
}
