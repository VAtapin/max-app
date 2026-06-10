<?php

require_once __DIR__ . '/../app/core/auth.php';

logout_admin();
redirect('login.php');
