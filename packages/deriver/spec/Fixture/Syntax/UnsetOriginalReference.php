<?php

function subject() { $a = 7; $b =& $a; unset($a); return $b; }
