<?php

namespace CallPaths;

function foo($a, $b)
{
    return $a . $b;
}

function bar($x)
{
    foo($x, ':one');
    foo($x, ':two');
}

function entry()
{
    bar('selected');
    gate(true);
}

function other()
{
    bar('other');
    gate(false);
}

function gate($flag)
{
    return $flag ? 11 : 27;
}
