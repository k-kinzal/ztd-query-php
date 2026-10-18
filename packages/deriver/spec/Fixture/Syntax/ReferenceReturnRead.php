<?php

function &slot(&$value) { return $value; }
function subject() { $value = 7; $alias =& slot($value); return $alias; }
