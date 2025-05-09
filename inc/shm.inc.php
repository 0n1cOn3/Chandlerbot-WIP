<?php
$shm_key = fileinode("bin/chandlerbot.php");
$shm = shm_attach($shm_key, $globalsettings["bot"]["shm_memory"]);

$initial_values = [
    1 => 0,               // allforwards
    2 => 0,               // queue
    3 => "",              // forward status
    4 => [],              // channelinfonew
    5 => $startTime       // startTime
];

foreach ($initial_values as $key => $value) {
    shm_put_var($shm, $key, $value);
}
