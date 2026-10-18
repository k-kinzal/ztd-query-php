<?php

function subject() { $a = 1; $b =& $a; $b = 7; return $a; }
