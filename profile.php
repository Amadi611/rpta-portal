<?php
require_once __DIR__.'/config.php'; require_login();
if(role()!=='passenger') redirect('dashboard.php');
redirect('register.php');
