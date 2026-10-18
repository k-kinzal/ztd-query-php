<?php

function subject() { try { throw new RuntimeException("failure"); } catch (RuntimeException $error) { return 7; } }
