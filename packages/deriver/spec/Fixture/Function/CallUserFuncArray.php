<?php

function subject() { return call_user_func_array(fn ($a, $b) => $a + $b, [3,4]); }
