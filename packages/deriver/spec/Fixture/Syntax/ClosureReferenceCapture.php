<?php

function subject() { $value = 1; $call = function () use (&$value) { $value = 7; }; $call(); return $value; }
