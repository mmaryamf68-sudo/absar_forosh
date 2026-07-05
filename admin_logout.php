<?php
require_once 'includes/auth.php';

init_secure_session();
destroy_session_fully();

header('Location: admin_login.php');
exit;
