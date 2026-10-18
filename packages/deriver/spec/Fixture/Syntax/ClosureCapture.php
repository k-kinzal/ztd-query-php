<?php

function subject() { $value = 7; $call = function () use ($value) { return $value; }; return $call(); }
