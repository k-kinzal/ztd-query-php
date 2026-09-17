<?php

declare(strict_types=1);

namespace LemonParser\Ast\Declaration;

/**
 * The keywords that take one argument and store it for the generated parser.
 *
 * These are the keywords Lemon handles in its `WAITING_FOR_DECL_ARG` state.
 *
 * @visibility public
 *
 * @example Recognising a keyword
 *     \LemonParser\Ast\Declaration\DirectiveKeyword::tryFrom('extra_argument')?->name // => "ExtraArgument"
 */
enum DirectiveKeyword: string
{
    case Name = 'name';
    case Include = 'include';
    case Code = 'code';
    case TokenDestructor = 'token_destructor';
    case DefaultDestructor = 'default_destructor';
    case TokenPrefix = 'token_prefix';
    case SyntaxError = 'syntax_error';
    case ParseAccept = 'parse_accept';
    case ParseFailure = 'parse_failure';
    case StackOverflow = 'stack_overflow';
    case ExtraArgument = 'extra_argument';
    case ExtraContext = 'extra_context';
    case TokenType = 'token_type';
    case DefaultType = 'default_type';
    case Realloc = 'realloc';
    case Free = 'free';
    case StackSize = 'stack_size';
    case StartSymbol = 'start_symbol';
}
