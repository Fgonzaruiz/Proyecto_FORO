<?php
require_once __DIR__ . '/../bootstrap.php';
require_once MYBB_ROOT . 'inc/functions_user.php';

global $db;
$prefix = TABLE_PREFIX;

$username = 'Narrador';
$password = 'Narrador!';
$email = 'narrador@localhost.com';

$password_fields = create_password($password);
$hashed_password = $password_fields['password'];
$salt = $password_fields['salt'];
$loginkey = generate_loginkey();

// Let's perform a simple insert with only the minimum required columns that are known to exist!
// username, password, salt, loginkey, email, usergroup, regdate, lastactive, lastvisit
$db->error_reporting = true; // Enable MyBB error reporting if any
$sql = "INSERT INTO {$prefix}users (
    username, password, salt, loginkey, email, usergroup, regdate, lastactive, lastvisit
) VALUES (
    'Narrador',
    '{$db->escape_string($hashed_password)}',
    '{$db->escape_string($salt)}',
    '{$db->escape_string($loginkey)}',
    '{$db->escape_string($email)}',
    4,
    " . time() . ",
    " . time() . ",
    " . time() . "
)";

echo "Running query...\n";
$res = $db->write_query($sql);
if ($res) {
    $uid = $db->insert_id();
    echo "Inserted successfully! UID: $uid\n";
} else {
    echo "Insert failed.\n";
}
unlink(__FILE__);
