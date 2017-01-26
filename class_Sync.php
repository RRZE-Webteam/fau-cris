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
        $message = __('Synchronisierung abgeschlossen:', 'fau-cris') . '<ul style="list-style-type: disc; padding-left: 40px;">';
        $lang = ($this->lang == 'en') ? '_en' : '';
        $pages = array();
        $page_field = array();
        $page_project = array();
        $num_created_p = 0;
        $num_updated_p = 0;
        $num_ok_p = 0;
        $num_created_m = 0;
        $num_updated_m = 0;
        $num_ok_m = 0;
        $num_created_mp = 0;
        $num_updated_mp = 0;
        $num_ok_mp = 0;
        $num_errors = 0;
        $title_research = __('Forschung', 'fau-cris');
        $page_template_portal = ( '' != locate_template('page-templates/page-portalindex.php')) ? 'page-templates/page-portalindex.php' : 'page.php';
        $page_template_nav = ( '' != locate_template('page-templates/page-subnav.php')) ? 'page-templates/page-subnav.php' : 'page.php';
        // Hauptmenü
        $menu_name = 'main-menu';
        $locations = get_nav_menu_locations();
        $this->menu_id = $locations[ $menu_name ] ;
        $this->menu_items = wp_get_nav_menu_items($this->menu_id);
        // Portalmenü
        $portal_name = 'Portal '.  $title_research;
        $portal_exists = wp_get_nav_menu_object( $portal_name );
        if( $portal_exists){
            $this->portal_id = $portal_exists->term_id;
            $this->portal_items = wp_get_nav_menu_items($portal_name);
        } else {
            $this->portal_id = wp_create_nav_menu($portal_name);
            $this->portal_items = array();
            $message .= '<li>' . sprintf(__('Portalmenü "%s" neu erstellt.'), $portal_name) . '</li>';
        }

        // Forschungsbereiche und -projekte auslesen -> Array
        $_f = new Forschungsbereiche();
        $fields = array();
        $fields = $_f->fieldsArray();
        $menu_position = -98;
        foreach ($fields as $field) {
            $_p = new Projekte();
            $projects = $_p->fieldProj($field->ID, 'array');
            $pages[$field->ID]['title'] = $field->attributes['cfname'.$lang];
            $pages[$field->ID]['position'] = $menu_position;
            $pages[$field->ID]['projects'] = array();
            $menu_position ++;
            if (!$projects)
                continue;
            foreach ($projects as $project) {
                $pages[$field->ID]['projects'][$project->ID]['title'] = $project->attributes['cftitle'.$lang];
                $pages[$field->ID]['projects'][$project->ID]['position'] = $menu_position;
                $menu_position ++;
            }
        }

        // Seite "Forschung" auf oberster Ebene
        $research_pages = get_pages(array('post_status' => 'publish'));
        foreach ($research_pages as $research_page) {
            if ($research_page->post_title == $title_research
                    && $research_page->post_parent == 0
                    && $research_page->post_status == 'publish') {
                $page_research[] = $research_page;
            }
        }
        //$research_contacts = array();
        require_once('class_Organisation.php');
        $orga = new Organisation();
        $research_contacts = $orga->researchContacts();
        if (!isset($page_research) || !count($page_research)) {
        // Seite Forschung existiert noch nicht -> anlegen
            $args = array(
                'post_content' => '[cris show=organisation]',
                'post_title' => $title_research,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_parent' => 0,
                'menu_order' => -99,
                'page_template' => $page_template_portal,
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
                $num_created_p ++;
                $message .= '<li>' . sprintf( __( 'Seite %s wurde erstellt.', 'fau-cris' ), $title_research ) . '</li>';
            }
        } else {
        // Seite Forschung existiert bereits
            $page_research = $page_research[0];
            $research_pid = $page_research->ID;
            $updated = false;
            // Wenn nötig Page-Template und Portalmenü einstellen
            $page_research_meta = get_post_meta($research_pid);
            if (!isset($page_research_meta['_wp_page_template'])
                    || $page_research_meta['_wp_page_template'][0] != $page_template_portal) {
                update_post_meta( $research_pid, '_wp_page_template', $page_template_portal );
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
                    || $page_research_meta['sidebar_personen'][0] != $research_contacts) {
                update_post_meta($research_pid, 'sidebar_personen', $research_contacts);
                $updated = true;
            }
            $updated ? $num_updated_p ++ : $num_ok_p ++;
        }
        // Wenn nötig Hauptmenü-Eintrag anlegen
        $research_menu_item = self::cris_menu_item_exists($this->menu_items, $title_research, 0, 0);
        if (!$research_menu_item) {
            $research_mid = self::cris_make_menu_item($this->menu_id, $title_research, $research_pid, 0, -99);
            $num_created_m ++;
        } else {
            if ($page_research->menu_order != -99) {
            // Wenn nötig existierende Menüposition korrigieren
                self::cris_make_menu_item($this->menu_id, $title_research, $research_pid, 0, -99, $research_menu_item->ID);
                $num_updated_m ++;
            } else {
                $num_ok_m ++;
            }
            $research_mid = $research_menu_item->ID;
        }

        // Seiten Forschungsbereiche unter Forschung
        foreach ($pages as $field_id => $field) {
            $field_pages = get_pages(array('child_of' => $research_pid, 'post_status' => 'publish'));
            $page_field = array();
            foreach ($field_pages as $field_page) {
                if ($field_page->post_title == $field['title']) {
                    $page_field[] = $field_page;
                }
            }
            if (!isset($page_field) || !count($page_field)) {
            // Seite Forschungsbereich existiert noch nicht -> anlegen
                $args = array(
                    'post_content' => "[cris show=fields field=$field_id hide=title]",
                    'post_title' => $field['title'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_parent' => $research_pid,
                    'menu_order' => $field['position'],
                    'page_template' => $page_template_nav,
                );
                $field_pid = wp_insert_post($args);
                if($field_pid) {
                    $message .= '<li>' . sprintf( __( 'Seite "%s" wurde erstellt.', 'fau-cris' ), $field['title'] ) . '</li>';
                    $num_created_p ++;
                } else {
                    $message .= '<li>' . sprintf( __( 'Seite "%s" konnten nicht erstellt werden.', 'fau-cris' ), $field['title'] ) . '</li>';
                    $num_errors ++;
                }
            } else {
            // Seite Forschungsbereich existiert bereits
                if ($page_field[0]->menu_order != $field['position']){
                // ggf. Seitenposition anpassen
                    wp_update_post(array(
                        'ID' => $page_field[0]->ID,
                        'menu_order' => $field['position']));
                    $num_updated_p ++;
                } else {
                    $num_ok_p ++;
                }
                $field_pid = $page_field[0]->ID;
            }
            // Wenn nötig Hauptmenü-Eintrag anlegen
            $field_menu_item = self::cris_menu_item_exists($this->menu_items, $field['title'], $research_mid);
            if (!$field_menu_item) {
                $field_mid = self::cris_make_menu_item($this->menu_id, $field['title'], $field_pid, $research_mid, $field['position']);
                $num_created_m ++;
            } else {
                $field_mid = $field_menu_item->ID;
                if ($field_menu_item->menu_order + ($field['position']*-1) != 99) {
                    // Wenn nötig existierende Menüposition korrigieren
                    self::cris_make_menu_item($this->menu_id, $field['title'], $field_pid, $research_mid, $field['position'], $field_menu_item->ID);
                    $num_updated_m ++;
                } else {
                    $num_ok_m ++;
                }
            }
            // Wenn nötig Portalmenü-Eintrag anlegen
            $field_portal_item = self::cris_menu_item_exists($this->portal_items, $field['title'], 0);
            if (!$field_portal_item) {
                $field_mpid = self::cris_make_menu_item($this->portal_id, $field['title'], $field_pid, 0, $field['position']);
                $num_created_mp ++;
            } else {
                $field_mpid = $field_portal_item->ID;
                if ($field_portal_item->menu_order + ($field['position']*-1) != 99) {
                // Wenn nötig existierende Menüposition korrigieren
                    self::cris_make_menu_item($this->portal_id, $field['title'], $field_pid, 0, $field['position'], $field_portal_item->ID);
                    $num_updated_mp ++;
                } else {
                    $num_ok_mp ++;
                }
            }

            // Seiten Forschungsprojekte innerhalb der Forschungsbereiche
            $projects = $field['projects'];
            foreach ($projects as $project_id => $project) {
                $project_pages = get_pages(array('child_of' => $field_pid, 'post_status' => 'publish'));
                $page_project = array();
                foreach ($project_pages as $project_page) {
                    if ($project_page->post_title == $project['title']) {
                        $page_project[] = $project_page;
                    }
                }
                if (!isset($page_project) || !count($page_project)) {
                // Seite Forschungsprojekt existiert noch nicht -> anlegen
                    $args = array(
                        'post_content' => "[cris show=projects project=$project_id]",
                        'post_title' => $project['title'],
                        'post_status' => 'publish',
                        'post_type' => 'page',
                        'post_parent' => $field_pid,
                        'menu_order' => $project['position'],
                        'page_template' => $page_template_nav,
                    );
                    $project_pid = wp_insert_post($args);
                    if($project_pid) {
                        $message .= '<li>' . sprintf( __( 'Seite "%s" wurde erstellt.', 'fau-cris' ), $project['title'] ) . '</li>';
                        $num_created_p ++;
                    } else {
                        $message .= '<li>' . sprintf( __( 'Seite "%s" konnte nicht erstellt werden.', 'fau-cris' ), $project['title'] ) . '</li>';
                        $num_errors ++;
                    }
                } else {
                // Seite Forschungsprojekt existiert bereits
                    if ($page_project[0]->menu_order != $project['position']){
                    // ggf. Seitenposition anpassen
                        wp_update_post(array(
                            'ID' => $page_project[0]->ID,
                            'menu_order' => $project['position']));
                        $num_updated_p ++;
                    } else {
                        $num_ok_p ++;
                    }
                    $project_pid = $page_project[0]->ID;
                }
                // Wenn nötig Hauptmenü-Eintrag anlegen
                $project_menu_item = self::cris_menu_item_exists($this->menu_items, $project['title'], $field_mid);
                if (!$project_menu_item) {
                    $project_mid = self::cris_make_menu_item($this->menu_id, $project['title'], $project_pid, $field_mid, $project['position']);
                    $num_created_m ++;
                } else {
                    $project_mid = $project_menu_item->ID;
                    if ($project_menu_item->menu_order + ($project['position']*-1) != 99) {
                    // Wenn nötig existierende Menüposition korrigieren
                        self::cris_make_menu_item($this->menu_id, $project['title'], $project_pid, $field_mid, $project['position'], $project_menu_item->ID);
                        $num_updated_m ++;
                    } else {
                        $num_ok_m ++;
                    }
                }
                // Wenn nötig Portalmenü-Eintrag anlegen
                $project_portal_item = self::cris_menu_item_exists($this->portal_items, $project['title'], $field_mpid);
                if (!$project_portal_item) {
                    $project_mpid = self::cris_make_menu_item($this->portal_id, $project['title'], $project_pid, $field_mpid, $project['position']);
                    $num_created_mp ++;
                } else {
                    $project_mpid = $project_portal_item->ID;
                    if ($project_portal_item->menu_order + ($project['position']*-1) != 99) {
                    // Wenn nötig existierende Menüposition korrigieren
                       self::cris_make_menu_item($this->portal_id, $project['title'], $project_pid, $field_mpid, $project['position'], $project_portal_item->ID);
                        $num_updated_mp ++;
                    } else {
                        $num_ok_mp ++;
                    }
                }
            }
        }

        // Admin-Notice: Synchronisation erfolgreich
        $message .= '<li>' . __('Seiten', 'fau-cris') . ': <span style="font-weight:normal;">' . sprintf( __( '%1d vorhanden, %2d aktualisiert, %3d neu', 'fau-cris' ), $num_ok_p, $num_updated_p, $num_created_p ) . '</span></li>';
        $message .= '<li>' . __('Menüeinträge', 'fau-cris') . ': <span style="font-weight:normal;">' . sprintf( __( '%1d vorhanden, %2d aktualisiert, %3d neu', 'fau-cris' ), $num_ok_m, $num_updated_m, $num_created_m ) . '</span></li>';
        $message .= '<li>' . __('Portalmenüeinträge', 'fau-cris') . ': <span style="font-weight:normal;">' . sprintf( __( '%1d vorhanden, %2d aktualisiert, %3d neu', 'fau-cris' ), $num_ok_mp, $num_updated_mp, $num_created_mp ) . '</span></li>';
        if ($num_errors > 0)
                $message .= '<li>' . sprintf( __( '%d Seite(n) konnten nicht erstellt werden.', 'fau-cris' ), $num_errors ) . '</li>';
        $message .= '</ul>';
        add_settings_error('AutoSyncComplete', 'autosynccomplete', $message , 'updated' );
        settings_errors();
    }

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
}
