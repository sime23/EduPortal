<?php
// Root entry point – redirect all traffic to the public/ application
header('Location: public/index.php', true, 302);
exit;
