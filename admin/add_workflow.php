<?php
// Backwards-compat shim: some pages/linking expect add_workflow.php
// Delegate to the full workflow builder implemented in manage_workflow.php
require_once __DIR__ . '/manage_workflow.php';
