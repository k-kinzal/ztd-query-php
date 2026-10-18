<?php

function subject() { $value = 1; $call = function () use (&$value) { return $value; }; $value = 7; return $call(); }
