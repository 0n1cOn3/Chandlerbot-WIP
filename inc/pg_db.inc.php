<?php

function pg_connect_db($globalsettings) {
    $conn_string = "host={$globalsettings['db']['dbhost']} port={$globalsettings['db']['dbport']} dbname={$globalsettings['db']['dbname']} user={$globalsettings['db']['dbuser']}";
    if (!empty($globalsettings['db']['dbpass'])) {
        $conn_string .= " password={$globalsettings['db']['dbpass']}";
    }

    $pg_conn = pg_connect($conn_string);
    if (!$pg_conn) {
        throw new Exception('Could not connect to the database: ' . pg_last_error());
    }

    pg_query($pg_conn, "SET application_name = 'chandlerbot php'");
    return $pg_conn;
}

function create_index_if_not_exists($tablename, $columns) {
    global $globalsettings;

    $sql = '';
    foreach ($columns as $column) {
        $sql .= "CREATE INDEX IF NOT EXISTS {$tablename}_idx_{$column} ON {$tablename}({$column}); ";
    }

    pg_query($globalsettings["db"]["pg_conn"], $sql);
}

function create_indexes_for_all_chan_tables() {
    global $globalsettings;

    $sql = "SELECT tablename FROM pg_tables WHERE tablename LIKE 'chan_%'";
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);
    
    while ($row = pg_fetch_array($result)) {
        $columns = ['mime_type', 'duration', 'width', 'height', 'size', 'filedate', 'filename', 'type', 'topicname'];
        create_index_if_not_exists($row["tablename"], $columns);
    }
}

function alter_table_add_columns($table, $columns) {
    global $globalsettings;

    foreach ($columns as $column => $default_value) {
        $sql = "ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS {$column} text DEFAULT '{$default_value}';";
        pg_query($globalsettings["db"]["pg_conn"], $sql);
    }
}

function create_table_if_not_exists($sql) {
    global $globalsettings;
    pg_query($globalsettings["db"]["pg_conn"], $sql);
}

function create_required_tables() {
    global $globalsettings;

    $tables = [
        "deletemessages" => "
            CREATE TABLE IF NOT EXISTS deletemessages (
                channelid BIGINT NOT NULL,
                msgid BIGINT NOT NULL,
                timestamp BIGINT NOT NULL,
                CONSTRAINT deletemessages_pkey PRIMARY KEY (channelid, msgid)
            );
        ",
        "repostmsg" => "
            CREATE TABLE IF NOT EXISTS repostmsg (
                channelid TEXT NOT NULL,
                lastruntime BIGINT NOT NULL,
                CONSTRAINT repostmsg_pkey PRIMARY KEY (channelid)
            );
        ",
        // Add other tables as needed
    ];

    foreach ($tables as $name => $sql) {
        create_table_if_not_exists($sql);
    }

    // Insert default settings
    $settings = [
        'floodwait' => '123',
        'forwardstopmsg' => '123',
        'alltimeforwards' => '1',
    ];

    foreach ($settings as $variable => $value) {
        $sql = "INSERT INTO settings (variable, value) VALUES ($1, $2) ON CONFLICT (variable) DO NOTHING;";
        $stmt = pg_prepare($globalsettings["db"]["pg_conn"], "insert_setting_{$variable}", $sql);
        pg_execute($globalsettings["db"]["pg_conn"], "insert_setting_{$variable}", [$variable, $value]);
    }
}

function update_forwardqueue_sequence_if_empty() {
    global $globalsettings;

    $sql = "SELECT 1 FROM forwardqueue LIMIT 1;";
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);

    if (pg_num_rows($result) == 0) {
        $sql = "ALTER SEQUENCE forwardqueue_id_seq RESTART WITH 1;";
        pg_query($globalsettings["db"]["pg_conn"], $sql);
    }
}

function recover_forwardqueue($mode = -1) {
    global $globalsettings, $recoverqueue, $toForward;

    $toForward = [];
    if ($mode == 1) {
        $recoverqueue = "yes"; 
    }

    // Recover forward queue if necessary
    if ($recoverqueue == "yes") {
        $sql = "SELECT * FROM forwardqueue";
        $result = pg_query($globalsettings["db"]["pg_conn"], $sql);

        while ($row = pg_fetch_array($result)) {
            $toForward[$row["forwardkey"]] = [
                'from' => $row["sourceid"],
                'to' => $row["targetid"],
                'msgid' => $row["msgid"],
                'album' => $row["album"],
                'topic' => $row["to_topic"],
                'text' => $row["message"],
                'cover' => $row["cover"],
                'width' => $row["width"],
                'height' => $row["heigth"],
                'runtime' => $row["runtime"],
                'filedate' => $row["filedate"],
                'filesize' => $row["filesize"],
                'tochanname' => $row["tochanname"]
            ];
        }
    }

    if ($recoverqueue != "yes") {
        $sql = "DELETE FROM forwardqueue";
        pg_query($globalsettings["db"]["pg_conn"], $sql);
    }
}

function get_settings() {
    global $globalsettings, $interval, $floodwait, $forwardstopmsg, $alltimeforwards;

    $sql = "SELECT variable, value FROM settings";
    $result = pg_query($globalsettings["db"]["pg_conn"], $sql);

    while ($row = pg_fetch_array($result)) {
        switch ($row["variable"]) {
            case "floodwait":
                $interval["fwdStepper"]["lastruntime"] = $row["value"];
                $floodwait = $row["value"];
                break;
            case "forwardstopmsg":
                $forwardstopmsg = (substr((int)$row["value"] - time(), 0, 1) == "-" ) ? 0 : $row["value"];
                break;
            case "alltimeforwards":
                $alltimeforwards = $row["value"];
                break;
        }
    }
}

// Main execution flow
try {
    $globalsettings["db"]["pg_conn"] = pg_connect_db($globalsettings);
    create_required_tables();
    create_indexes_for_all_chan_tables();
    alter_table_add_columns('forwardqueue', [
        'tochanname' => 'default value',
        'channelname' => 'default value',
        'lastrun' => 'default value',
        'nextrun' => 'default value'
    ]);
    update_forwardqueue_sequence_if_empty();
    recover_forwardqueue();
    get_settings();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

?>
