<?php

function subject() { return (new class { public int $value = 7; })->value; }
