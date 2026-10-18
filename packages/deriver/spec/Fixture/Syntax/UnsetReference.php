<?php

function subject() { $a = 7; $b =& $a; unset($b); return $a; }
