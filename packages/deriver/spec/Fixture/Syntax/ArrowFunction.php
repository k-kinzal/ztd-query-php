<?php

function subject() { $value = 7; $call = fn () => $value; return $call(); }
