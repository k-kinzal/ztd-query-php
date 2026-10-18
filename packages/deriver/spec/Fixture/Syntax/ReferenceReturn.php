<?php

function &slot(&$value) { return $value; }
function subject() { $value = 1; $alias =& slot($value); $alias = 7; return $value; }
