<?php

function value(iterable $value) { foreach ($value as $item) { return $item; } return null; }
function subject() { return value([7]); }
