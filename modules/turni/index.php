<?php
// modules/turni/index.php
// Redirect del modulo Turni verso la schermata planning

require_once __DIR__ . '/../../core/settings.php';

require_login();
require_module('assignments');

redirect('modules/turni/planning.php');