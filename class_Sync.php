<?php

require_once("class_Forschungsbereiche.php");
require_once("class_Projekte.php");

class Sync {

    public function __construct() {
        $this->options = (array) get_option('_fau_cris');
        $this->orgNr = $this->options['cris_org_nr'];
        $this->lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
        $this->menu_items = array();
        $this->menu_id = '';
        $this->portal_items = array();
        $this->portal_id = '';
    }

    public function do_sync() {
        if (!$this->orgNr || $this->orgNr == 0) {
            // Admin-Notice: Synchronisation fehlgeschlagen
            add_settings_error('Automatische Synchronisation', 'cris_sync_check',  __('Synchronisierung fehlgeschlagen!<br />Bitte geben Sie im Reiter "Allgemein" die CRIS-ID Ihrer Organisationseinheit an.', 'fau-cris') , 'error' );
            settings_errors();
            return;
        }
        $this->message = __('Synchronisierung abgeschlossen:', 'fau-cris') . '<ul style="list-style-type: disc; padding-left: 40px;">';
        $lang = ($this->lang == 'en') ? '_en' : '';
        $pages = array();
        $page_field = array();
        $page_project = array();
        $this->menu_position = -99;
        $this->num_created_p = 0;
        $this->num_updated_p = 0;
        $this->num_ok_p = 0;
        $this->num_created_m = 0;
        $this->num_updated_m = 0;
        $this->num_ok_m = 0;
        $this->num_created_mp = 0;
        $this->num_updated_mp = 0;
        $this->num_ok_mp = 0;
        $this->num_errors = 0;
        $this->title_research = __('Forschung', 'fau-cris');
        $this->title_noFieldsPage = __('Weitere Projekte', 'fau-cris');
        $this->page_template_portal = ( '' != locate_template('page-templates/page-portalindex.php')) ? 'page-templates/page-portalindex.php' : 'page.php';
        $this->page_template_nav = ( '' != locate_template('page-templates/page-subnav.php')) ? 'page-templates/page-subnav.php' : 'page.php';
        // Hauptmenü
        $this->menu_name = 'main-menu';
        $locations = get_nav_menu_locations();
        $this->menu_id = $locations[ $this->menu_name ] ;
        $this->menu_items = wp_get_nav_menu_items($this->menu_id);
        // Portalmenü
        $portal_name = 'Portal '.  $this->title_research;
        $portal_exists = wp_get_nav_menu_object( $portal_name );
        if( $portal_exists){
            $this->portal_id = $portal_exists->term_id;
            $this->portal_items = wp_get_nav_menu_items($portal_name);
        } else {
            $this->portal_id = wp_create_nav_menu($portal_name);
            $this->portal_items = array();
            $this->message .= '<li>' . sprintf(__('Portalmenü "%s" neu erstellt.'), $portal_name) . '</li>';
        }

        /*
         * Seite "Forschung" auf oberster Ebene
         */

        $research_pages = get_pages(array('post_status' => 'publish'));
        foreach ($research_pages as $research_page) {
            if ($research_page->post_title == $this->title_research
                    && $research_page->post_parent == 0
                    && $research_page->post_status == 'publish') {
                $page_research[] = $research_page;
            }
        }
        require_once('class_Organisation.php');
        $orga = new Organisation();
        $research_contacts = $orga->researchContacts();
        if (!isset($page_research) || !count($page_research)) {
        // Seite Forschung existiert noch nicht -> anlegen
            $args = array(
                'post_content' => '[cris show=organisation]',
                'post_title' => $this->title_research,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_parent' => 0,
                'menu_order' => $this->menu_position,
                'page_template' => $this->page_template_portal,
                'meta_input' => array(
                    // wegen der chaotischen Benennung der Meta-Values, siehe custom-fields.php, Z. 673ff.
                    'fauval_portalmenu_thumbnailson' => 1,
                    'fauval_portalmenu_nofallbackthumb' => 1,
                    'fauval_portalmenu_nosub' => 0,
                    'portalmenu-slug' => $this->portal_id,
                    'sidebar_personen'=> $research_contacts
                )
            );
            $research_pid = wp_insert_post($args);
            if ($research_pid) {
                $this->num_created_p ++;
                $this->message .= '<li>' . sprintf( __( 'Seite %s wurde erstellt.', 'fau-cris' ), $this->title_research ) . '</li>';
            }
        } else {
        // Seite Forschung existiert bereits
            $page_research = $page_research[0];
            $research_pid = $page_research->ID;
            $updated = false;
            // Wenn nötig Page-Template und Portalmenü einstellen
            $page_research_meta = get_post_meta($research_pid);
            if (!isset($page_research_meta['_wp_page_template'])
                    || $page_research_meta['_wp_page_template'][0] != $this->page_template_portal) {
                update_post_meta( $research_pid, '_wp_page_template', $this->page_template_portal );
                $updated = true;
            }
            if (!isset($page_research_meta['fauval_portalmenu_thumbnailson'])
                    || $page_research_meta['fauval_portalmenu_thumbnailson'][0] != 1) {
                update_post_meta( $research_pid, 'fauval_portalmenu_thumbnailson', 1 );
                $updated = true;
            }
            if (!isset($page_research_meta['fauval_portalmenu_nofallbackthumb'])
                    || $page_research_meta['fauval_portalmenu_nofallbackthumb'][0] != 1) {
                update_post_meta( $research_pid, 'fauval_portalmenu_nofallbackthumb', 1 );
                $updated = true;
            }
            if (!isset($page_research_meta['fauval_portalmenu_nosub'])
                    || $page_research_meta['fauval_portalmenu_nosub'][0] != 0) {
                update_post_meta( $research_pid, 'fauval_portalmenu_nosub', 0 );
                $updated = true;
            }
            if (!isset($page_research_meta['portalmenu-slug'])
                    || $page_research_meta['portalmenu-slug'][0] != $this->portal_id) {
                update_post_meta( $research_pid, 'portalmenu-slug', $this->portal_id );
                $updated = true;
            }
            if (!isset($page_research_meta['sidebar_personen'])
                    || unserialize($page_research_meta['sidebar_personen'][0]) != $research_contacts) {
                update_post_meta($research_pid, 'sidebar_personen', $research_contacts);
                $updated = true;
            }
            $updated ? $this->num_updated_p ++ : $this->num_ok_p ++;
        }
        // Wenn nötig Hauptmenü-Eintrag anlegen
        $research_menu_item = self::cris_menu_item_exists($this->menu_items, $this->title_research, 0, 0);
        if (!$research_menu_item) {
            $research_mid = self::cris_make_menu_item($this->menu_id, $this->title_research, $research_pid, 0, $this->menu_position);
            $this->num_created_m ++;
        } else {
            if ($page_research->menu_order != $this->menu_position) {
            // Wenn nötig existierende Menüposition korrigieren
                self::cris_make_menu_item($this->menu_id, $this->title_research, $research_pid, 0, $this->menu_position, $research_menu_item->ID);
                $this->num_updated_m ++;
            } else {
                $this->num_ok_m ++;
            }
            $research_mid = $research_menu_item->ID;
        }
        $this->menu_position++;

        /*
         *  Seiten Forschungsbereiche und -projekte vorbereiten
         */

        $_f = new Forschungsbereiche();
        $fields = array();
        $fields = $_f->fieldsArray();
        if (!$fields || !is_array($fields)) {
            if( wp_next_scheduled( 'cris_auto_update' ))
                wp_clear_scheduled_hook('cris_auto_update');
            $this->message .= '<li>Es konnten keine Forschungsbereiche gefunden werden. Bitte legen Sie zunächst Forschungsbereiche und zugeordnete Projekte in CRIS an.</li>';

        }
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $_p = new Projekte();
                $projects = $_p->fieldProj($field->ID, 'array');
                $pages[$field->ID]['title'] = $field->attributes['cfname'.$lang];
                $pages[$field->ID]['position'] = $this->menu_position;
                $pages[$field->ID]['content'] = "[cris show=fields field=$field->ID]";
                $pages[$field->ID]['projects'] = array();
                $this->menu_position ++;
                if (!$projects)
                    continue;
                foreach ($projects as $project) {
                    $pages[$field->ID]['projects'][$project->ID]['title'] = $project->attributes['cftitle'.$lang];
                    $pages[$field->ID]['projects'][$project->ID]['position'] = $this->menu_position;
                    $pages[$field->ID]['projects'][$project->ID]['content'] = "[cris show=projects project=$project->ID]";
                    $this->menu_position ++;
                }
            }
        }

        /*
         *  Seiten Forschungsbereiche unter Forschung
         */

        foreach ($pages as $field_id => $field) {
            $field_page = self::cris_make_page($field['title'], $field['content'], $field['position'], $research_pid, $research_mid,0,1);

            /*
             *  Seiten Forschungsprojekte innerhalb der Forschungsbereiche
             */

            $projects = $field['projects'];
            foreach ($projects as $project_id => $project) {
                $project_page = self::cris_make_page($project['title'], $project['content'], $project['position'], $field_page['pid'], $field_page['mid'], $field_page['mpid'],1);
            }
        }

        /*
         * Projekte, die keinem Forschungsbereich zugeordnet sind
         */

        $pages['no_field']['title'] = $this->title_noFieldsPage;
        $pages['no_field']['content'] = '';
        $pages['no_field']['position'] = $this->menu_position;
        $pages['no_field']['projects'] = array();
        $this->menu_position ++;

        $field_projects = array();
        foreach($pages as $field) {
            foreach ($field['projects'] as $id => $project) {
                if (!empty($field['projects'])) {
                    $field_projects[$id] = $project;
                }
            }

        }
        $p = new CRIS_projects;
        $all_projects = $p->by_orga_id($this->orgNr);
        $orga_projects = array();
        foreach ($all_projects as $a_p) {
            $orga_projects[$a_p->ID]['title'] = $a_p->attributes['cftitle'.$lang];
            $orga_projects[$a_p->ID]['position'] = $this->menu_position;
            $orga_projects[$a_p->ID]['content'] = "[cris show=projects project=$a_p->ID]";
            $this->menu_position ++;
        }
        foreach ($orga_projects as $o_p => $details) {
            if (!array_key_exists($o_p, $field_projects)) {
                $pages['no_field']['projects'][$o_p] = $details;
            }
        }
        // Seite "Weitere Projekte"
        if (count($pages['no_field']['projects'])) {
            $proj_id_string = implode(',', array_keys($pages['no_field']['projects']));
            $pages['no_field']['content'] = "[cris show=projects project=$proj_id_string]";
            $no_field_page = self::cris_make_page($this->title_noFieldsPage, $pages['no_field']['content'], $pages['no_field']['position'], $research_pid, $research_mid, 0,1);

            // Seiten Forschungsprojekte unter "Weitere Projekte"
            $no_field_projects = $pages['no_field']['projects'];
            foreach ($no_field_projects as $project_id => $project) {
                $no_field_project_page = self::cris_make_page($project['title'], $project['content'], $project['position'], $no_field_page['pid'], $no_field_page['mid'], $no_field_page['mpid'],1);
            }
        }


        /*
         *  Admin-Notice: Synchronisation erfolgreich
         */
        $this->message .= '<li>' . __('Seiten', 'fau-cris') . ': <span style="font-weight:normal;">' . sprintf( __( '%1d vorhanden, %2d aktualisiert, %3d neu', 'fau-cris' ), $this->num_ok_p, $this->num_updated_p, $this->num_created_p ) . '</span></li>';
        $this->message .= '<li>' . __('Menüeinträge', 'fau-cris') . ': <span style="font-weight:normal;">' . sprintf( __( '%1d vorhanden, %2d aktualisiert, %3d neu', 'fau-cris' ), $this->num_ok_m, $this->num_updated_m, $this->num_created_m ) . '</span></li>';
        $this->message .= '<li>' . __('Portalmenüeinträge', 'fau-cris') . ': <span style="font-weight:normal;">' . sprintf( __( '%1d vorhanden, %2d aktualisiert, %3d neu', 'fau-cris' ), $this->num_ok_mp, $this->num_updated_mp, $this->num_created_mp ) . '</span></li>';
        if ($this->num_errors > 0)
                $this->message .= '<li>' . sprintf( __( '%d Seite(n) konnten nicht erstellt werden.', 'fau-cris' ), $this->num_errors ) . '</li>';
        $this->message .= '</ul>';
        add_settings_error('AutoSyncComplete', 'autosynccomplete', $this->message , 'updated' );
        settings_errors();
    }


    /*
     * Helfer-Funktionen
     */

    private function cris_menu_item_exists($menu, $title, $parent = 0) {
        foreach ($menu as $menu_item) {
            if ($menu_item->title == $title
                    && $menu_item->menu_item_parent == $parent
                    && !isset($menu_item->_invalid)) {
                return $menu_item;
            }
        }
        return;
    }

    private function cris_make_menu_item($menu, $title, $object_id, $parent_id, $position = 0, $menu_item_db_id = 0) {
        $mid = wp_update_nav_menu_item($menu, $menu_item_db_id, array(
            //'menu-item-db-id' => '',
            'menu-item-object-id' => $object_id,
            'menu-item-object' => 'page',
            'menu-item-parent-id' => $parent_id,
            'menu-item-position' => $position,
            'menu-item-type' => 'post_type',
            'menu-item-title' => $title,
            //'menu-item-url' => $link,
            //'menu-item-description' => '',
            //'menu-item-attr-title' => '',
            //'menu-item-target' => '',
            'menu-item-classes' => 'cris',
            //'menu-item-xfn' => '',
            'menu-item-status' => 'publish',
            )
        );
        if (is_int($mid))
            return $mid;
    }

    private function cris_make_page($title, $content, $position, $parent_pid, $parent_mid, $parent_mpid, $portal = 1) {
        $pages = get_pages(array('child_of' => $parent_pid, 'post_status' => 'publish'));
        $pages_array = array();
        foreach ($pages as $page) {
            if ($page->post_title == $title) {
                $pages_array[] = $page;
            }
        }
        if (!isset($pages_array) || !count($pages_array)) {
        // Seite Forschungsprojekt existiert noch nicht -> anlegen
            $args = array(
                'post_content' => $content,
                'post_title' => $title,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_parent' => $parent_pid,
                'menu_order' => $position,
                'page_template' => $this->page_template_nav,
            );
            $pid = wp_insert_post($args);
            if($pid) {
                $this->message .= '<li>' . sprintf( __( 'Seite "%s" wurde erstellt.', 'fau-cris' ), $title ) . '</li>';
                $this->num_created_p ++;
            } else {
                $this->message .= '<li>' . sprintf( __( 'Seite "%s" konnte nicht erstellt werden.', 'fau-cris' ), $title ) . '</li>';
                $this->num_errors ++;
            }
        } else {
        // Seite Forschungsprojekt existiert bereits
            if ($pages_array[0]->menu_order != $position){
            // ggf. Seitenposition anpassen
                wp_update_post(array(
                    'ID' => $pages_array[0]->ID,
                    'menu_order' => $position));
                $this->num_updated_p ++;
            } else {
                $this->num_ok_p ++;
            }
            $pid = $pages_array[0]->ID;
        }
        // Wenn nötig Hauptmenü-Eintrag anlegen
        $menu_item = self::cris_menu_item_exists($this->menu_items, $title, $parent_mid);
        if (!$menu_item) {
            $mid = self::cris_make_menu_item($this->menu_id, $title, $pid, $parent_mid, $position);
            $this->num_created_m ++;
        } else {
            $mid = $menu_item->ID;
            if ($menu_item->menu_order + ($position*-1) != 99) {
            // Wenn nötig existierende Menüposition korrigieren
                self::cris_make_menu_item($this->menu_id, $title, $pid, $parent_mid, $position, $menu_item->ID);
                $this->num_updated_m ++;
            } else {
                $this->num_ok_m ++;
            }
        }
        if ($portal == 1) {
            // Wenn nötig Portalmenü-Eintrag anlegen
            $portal_item = self::cris_menu_item_exists($this->portal_items, $title, $parent_mpid);
            if (!$portal_item) {
                $mpid = self::cris_make_menu_item($this->portal_id, $title, $pid, $parent_mpid, $position);
                $this->num_created_mp ++;
            } else {
                $mpid = $portal_item->ID;
                if ($portal_item->menu_order + ($position*-1) != 99) {
                // Wenn nötig existierende Menüposition korrigieren
                   self::cris_make_menu_item($this->portal_id, $title, $pid, $parent_mpid, $position, $portal_item->ID);
                    $this->num_updated_mp ++;
                } else {
                    $this->num_ok_mp ++;
                }
            }
        } else {
            $mpid = 0;
        }
        $ids = array(
            'pid'  => $pid,
            'mid'  => $mid,
            'mpid' => $mpid
        );
        return $ids;
    }
}
