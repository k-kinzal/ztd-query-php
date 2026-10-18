<?php

function assign(&$value) { $value = 7; }
function subject() { $value = 1; assign($value); return $value; }
