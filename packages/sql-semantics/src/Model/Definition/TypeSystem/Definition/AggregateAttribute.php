<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Definition;

/**
 * An attribute of an aggregate definition.
 * @visibility public
 * @example Choosing a parallel safety keyword
 *     \SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute::Parallel->choose('safe') // => 'safe'
 *     \SqlSemantics\Model\Definition\TypeSystem\Definition\AggregateAttribute::Parallel->choose('SAFE') // => null
 */
enum AggregateAttribute: string implements DefinitionAttribute
{
    case Sfunc = 'SFUNC';
    case Stype = 'STYPE';
    case Sspace = 'SSPACE';
    case Finalfunc = 'FINALFUNC';
    case FinalfuncExtra = 'FINALFUNC_EXTRA';
    case FinalfuncModify = 'FINALFUNC_MODIFY';
    case Combinefunc = 'COMBINEFUNC';
    case Serialfunc = 'SERIALFUNC';
    case Deserialfunc = 'DESERIALFUNC';
    case Initcond = 'INITCOND';
    case Msfunc = 'MSFUNC';
    case Minvfunc = 'MINVFUNC';
    case Mstype = 'MSTYPE';
    case Msspace = 'MSSPACE';
    case Mfinalfunc = 'MFINALFUNC';
    case MfinalfuncExtra = 'MFINALFUNC_EXTRA';
    case MfinalfuncModify = 'MFINALFUNC_MODIFY';
    case Minitcond = 'MINITCOND';
    case Sortop = 'SORTOP';
    case Parallel = 'PARALLEL';
    case Hypothetical = 'HYPOTHETICAL';

    /**
     * The keywords of the choice attributes.
     */
    public const CHOICES = ['FINALFUNC_MODIFY' => ['read_only', 'shareable', 'read_write'], 'MFINALFUNC_MODIFY' => ['read_only', 'shareable', 'read_write'], 'PARALLEL' => ['safe', 'restricted', 'unsafe']];

    /**
     * The attribute name as written in SQL.
     */
    public function spelling(): string
    {
        return $this->value;
    }

    /**
     * Support functions are names, state types are types, and the remaining attributes are sizes, flags, text, an operator, or keywords.
     */
    public function kind(): DefinitionKind
    {
        return match ($this) {
            self::Sfunc, self::Finalfunc, self::Combinefunc, self::Serialfunc, self::Deserialfunc, self::Msfunc, self::Minvfunc, self::Mfinalfunc => DefinitionKind::Name,
            self::Stype, self::Mstype => DefinitionKind::Type,
            self::Sspace, self::Msspace => DefinitionKind::Integer,
            self::FinalfuncExtra, self::MfinalfuncExtra, self::Hypothetical => DefinitionKind::Boolean,
            self::Initcond, self::Minitcond => DefinitionKind::Text,
            self::Sortop => DefinitionKind::Operator,
            self::FinalfuncModify, self::MfinalfuncModify, self::Parallel => DefinitionKind::Choice,
        };
    }

    /**
     * The modify and parallel keywords match exactly, in lowercase, as the server matches them.
     */
    public function choose(string $text): ?string
    {
        return in_array($text, self::CHOICES[$this->value] ?? [], true) ? $text : null;
    }

    /**
     * The attributes of the moving-aggregate mode, which require MSTYPE.
     */
    public function moving(): bool
    {
        return in_array($this, [self::Msfunc, self::Minvfunc, self::Msspace, self::Mfinalfunc, self::MfinalfuncExtra, self::MfinalfuncModify, self::Minitcond], true);
    }
}
