<?php

// Set PHP execution time limits for long-running API calls
ini_set('max_execution_time', 120); // 2 minutes
ini_set('memory_limit', '256M'); // Increase memory limit
ini_set('default_socket_timeout', 60); // Socket timeout

echo "PHP execution time limits updated:\n";
echo "- max_execution_time: " . ini_get('max_execution_time') . " seconds\n";
echo "- memory_limit: " . ini_get('memory_limit') . "\n";
echo "- default_socket_timeout: " . ini_get('default_socket_timeout') . " seconds\n"; 