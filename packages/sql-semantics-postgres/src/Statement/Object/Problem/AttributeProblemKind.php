<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Problem;

/**
 * What is wrong with the attributes of a definition, as the defining command reports it.
 *
 * Unrecognized attributes of an operator, an aggregate and a base type draw
 * a warning, kept for backwards compatibility; every other problem is an
 * error.
 * Source: `DefineOperator` in `src/backend/commands/operatorcmds.c`, `DefineAggregate` in `src/backend/commands/aggregatecmds.c`,
 * `DefineType` and `DefineRange` in `src/backend/commands/typecmds.c`, `DefineCollation` in `src/backend/commands/collationcmds.c`,
 * the text search commands in `src/backend/commands/tsearchcmds.c` and `src/backend/commands/define.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Telling a warning from an error
 *     [\SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblemKind::UnrecognizedOperatorAttribute->warning(), \SqlSemantics\Platform\PostgreSql\Statement\Object\Problem\AttributeProblemKind::UnrecognizedRangeAttribute->warning()] // => [true, false]
 */
enum AttributeProblemKind
{
    case UnrecognizedOperatorAttribute;
    case UnrecognizedAggregateAttribute;
    case UnrecognizedTypeAttribute;
    case UnrecognizedRangeAttribute;
    case UnrecognizedCollationAttribute;
    case UnrecognizedParserParameter;
    case UnrecognizedTemplateParameter;
    case UnrecognizedConfigurationParameter;
    case MissingParameter;
    case NotAName;
    case NotATypeName;
    case NotABoolean;
    case NotAnInteger;
    case InvalidLength;
    case InvalidParallel;
    case InvalidModify;
    case InvalidAlignment;
    case InvalidStorage;
    case InvalidProvider;
    case SetofArgument;
    case InvalidCategory;
    case Conflicting;
    case MissingOperatorFunction;
    case MissingArgumentTypes;
    case MissingRightArgument;
    case MissingAggregateAttribute;
    case MissingMovingAttribute;
    case MovingWithoutState;
    case HypotheticalPlain;
    case MissingInputType;
    case RedundantBaseType;
    case HalfSerialization;
    case MissingInputFunction;
    case MissingOutputFunction;
    case ModifierOutputAlone;
    case MissingSubtype;
    case MissingCollationParameter;
    case Nondeterministic;
    case RulesWithoutIcu;
    case MissingParserMethod;
    case MissingLexize;
    case MissingTemplate;
    case MissingParser;
    case ParserAndCopy;

    /**
     * The message of each problem, with `%s` for its subjects.
     */
    private const MESSAGES = [
        'UnrecognizedOperatorAttribute' => 'operator attribute "%s" not recognized',
        'UnrecognizedAggregateAttribute' => 'aggregate attribute "%s" not recognized',
        'UnrecognizedTypeAttribute' => 'type attribute "%s" not recognized',
        'UnrecognizedRangeAttribute' => 'type attribute "%s" not recognized',
        'UnrecognizedCollationAttribute' => 'collation attribute "%s" not recognized',
        'UnrecognizedParserParameter' => 'text search parser parameter "%s" not recognized',
        'UnrecognizedTemplateParameter' => 'text search template parameter "%s" not recognized',
        'UnrecognizedConfigurationParameter' => 'text search configuration parameter "%s" not recognized',
        'MissingParameter' => '%s requires a parameter',
        'NotAName' => 'argument of %s must be a name',
        'NotATypeName' => 'argument of %s must be a type name',
        'NotABoolean' => '%s requires a Boolean value',
        'NotAnInteger' => '%s requires an integer value',
        'InvalidLength' => 'invalid argument for %s: "%s"',
        'InvalidParallel' => 'parameter "parallel" must be SAFE, RESTRICTED, or UNSAFE',
        'InvalidModify' => 'parameter "%s" must be READ_ONLY, SHAREABLE, or READ_WRITE',
        'InvalidAlignment' => 'alignment "%s" not recognized',
        'InvalidStorage' => 'storage "%s" not recognized',
        'InvalidProvider' => 'unrecognized collation provider: %s',
        'SetofArgument' => 'SETOF type not allowed for operator argument',
        'InvalidCategory' => 'invalid type category "%s": must be simple ASCII',
        'Conflicting' => 'conflicting or redundant options',
        'MissingOperatorFunction' => 'operator function must be specified',
        'MissingArgumentTypes' => 'operator argument types must be specified',
        'MissingRightArgument' => 'operator right argument type must be specified',
        'MissingAggregateAttribute' => 'aggregate %s must be specified',
        'MissingMovingAttribute' => 'aggregate %s must be specified when mstype is specified',
        'MovingWithoutState' => 'aggregate %s must not be specified without mstype',
        'HypotheticalPlain' => 'only ordered-set aggregates can be hypothetical',
        'MissingInputType' => 'aggregate input type must be specified',
        'RedundantBaseType' => 'basetype is redundant with aggregate input type specification',
        'HalfSerialization' => 'must specify both or neither of serialization and deserialization functions',
        'MissingInputFunction' => 'type input function must be specified',
        'MissingOutputFunction' => 'type output function must be specified',
        'ModifierOutputAlone' => 'type modifier output function is useless without a type modifier input function',
        'MissingSubtype' => 'type attribute "subtype" is required',
        'MissingCollationParameter' => 'parameter "%s" must be specified',
        'Nondeterministic' => 'nondeterministic collations not supported with this provider',
        'RulesWithoutIcu' => 'ICU rules cannot be specified unless locale provider is ICU',
        'MissingParserMethod' => 'text search parser %s method is required',
        'MissingLexize' => 'text search template lexize method is required',
        'MissingTemplate' => 'text search template is required',
        'MissingParser' => 'text search parser is required',
        'ParserAndCopy' => 'cannot specify both PARSER and COPY options',
    ];

    /**
     * Answers the message with `%s` for each subject.
     */
    public function format(): string
    {
        return self::MESSAGES[$this->name];
    }

    /**
     * Tells whether the server only warns and goes on.
     */
    public function warning(): bool
    {
        return in_array($this, [self::UnrecognizedOperatorAttribute, self::UnrecognizedAggregateAttribute, self::UnrecognizedTypeAttribute], true);
    }
}
