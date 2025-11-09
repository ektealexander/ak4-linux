<?php
if (!getenv('DB_HOST')) {
    putenv('DB_HOST=db');
}
if (!getenv('DB_NAME')) {
    putenv('DB_NAME=varehusdb');
}
if (!getenv('DB_USER')) {
    putenv('DB_USER=webuser');
}
if (!getenv('DB_PASS')) {
    putenv('DB_PASS=Passord123');
}

$requiredVars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
foreach ($requiredVars as $var) {
    if (empty(getenv($var))) {
        error_log("Warning: Database configuration variable $var is not set");
    }
}