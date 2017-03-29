<?php

class CRIS_DB {

    public function __construct() {

        $options = get_site_option(FAU_CRIS::site_option_name);

        $hostname = $options['dbhost'];
        $username = $options['dbuser'];
        $password = $options['dbpw'];
        $database = $options['dbname'];

        $this->db = new mysqli($hostname, $username, $password, $database);
        if ($this->db->connect_error) {
            wp_die('Connect Error: ' . $this->db->connect_error);
        }
    }

    public function get($query) {
        $db = $this->db;
        //var_dump($db);
        $result = $db->query($query);
        $items = array();
        //var_dump($result);
        if($result){
            //printf("Select returned %d rows.\n", $result->num_rows);
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }
            $result->close();
            if ($db->more_results()) {
                $db->next_result();
            }
            //var_dump($items);
            return $items;
        }
        return false;
    }
}