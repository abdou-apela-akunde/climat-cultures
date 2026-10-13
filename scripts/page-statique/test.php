<?php
echo "Climat & Cultures - Équipe 7 - PHP " . PHP_VERSION . " OK";
echo "<br>SQLite PDO : " . (in_array('sqlite', PDO::getAvailableDrivers()) ? "disponible" : "absent");