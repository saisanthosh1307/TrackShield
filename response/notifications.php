<?php
require_once __DIR__ . '/../includes/auth.php';
$user = require_role('response');
require __DIR__ . '/../includes/notifications-view.php';
