<?php

declare(strict_types=1);

namespace Spec\Policy;

/**
 * Labels whether a witness was accepted, rejected for external reasons, or
 * exposed a syntax or contract problem in the generated SQL.
 */
enum OutcomeKind: string
{
    case Accepted = 'accepted';
    case State = 'state';
    case Environment = 'environment';
    case Resource = 'resource';
    case Contract = 'contract';
    case Syntax = 'syntax';
    case Unknown = 'unknown';
}
