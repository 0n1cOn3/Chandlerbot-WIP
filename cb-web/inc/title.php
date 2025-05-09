<?php

function title() {
    global $botconf;
    if (array_key_exists("botcommand", yaml_parse_file($botconf))) { 
        echo yaml_parse_file($botconf)["botcommand"];
 }  else exit(1);
} // function end 
