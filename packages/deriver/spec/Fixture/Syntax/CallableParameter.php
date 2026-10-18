<?php

function invoke(callable $call) { return $call(); }
function subject() { return invoke(fn () => 7); }
