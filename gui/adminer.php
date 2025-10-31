<?php
/**
 * Adminer Wrapper with SSL Support
 * This wrapper ensures Adminer respects SSL settings
 */

require_once 'includes/functions.php';

// Redirect to SSL URL if dashboard has SSL enabled and request is from :9000
redirectToSSLIfEnabled();

// Load the actual Adminer application
require_once 'adminer-core.php';
