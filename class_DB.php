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
        $result = $db->query($query);
        $items = array();
        if($result){
            while ($row = $result->fetch_array()){
                $items[] = $row;
           }
           $result->close();
           if ($db->more_results()) {
               $db->next_result();
           }
           return $items;
        }
        return false;
    }
}