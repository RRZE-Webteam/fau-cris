<?php

require_once("class_Forschungsbereiche.php");
require_once("class_Projekte.php");

class Sync {

    public function __construct() {
        $this->options = (array) get_option('_fau_cris');
        $this->orgNr = $this->options['cris_org_nr'];
        $this->lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
    }

    public function do_sync() {
        if (!$this->orgNr || $this->orgNr == 0) {
            // Admin-Notice: Synchronisation fehlgeschlagen
            add_settings_error('Automatische Synchronisation', 'cris_sync_check',  __('Synchronisierung fehlgeschlagen!<br />Bitte geben Sie im Reiter "Allgemein" die CRIS-ID Ihrer Organisationseinheit an.', 'fau-cris') , 'error' );
            settings_errors();
            return;
        }
        $message = __('Synchronisierung abgeschlossen:', 'fau-cris');
        $lang = ($this->lang == 'en') ? '_en' : '';
        $pages = array();
        $num_created = 0;
        $num_updated = 0;
        $num_errors = 0;

        $_f = new Forschungsbereiche();
        $fields = $_f->fieldsArray();
        foreach ($fields as $field) {
            $_p = new Projekte();
            $projects = $_p->fieldProj($field->ID, 'array');
            foreach ($projects as $project) {
                $pages[$field->ID]['title'] = $field->attributes['cfname'.$lang];
                $pages[$field->ID]['projects'][$project->ID] = $project->attributes['cftitle'.$lang];
            }
        }
        // Seite "Forschung" auf oberster Ebene
        $title_forschung = __('Forschung', 'fau-cris');
        $page_template_portal = ( '' != locate_template('page-templates/page-portalindex.php')) ? 'page-templates/page-portalindex.php' : 'page.php';
        $page_template_nav = ( '' != locate_template('page-templates/page-subnav.php')) ? 'page-templates/page-subnav.php' : 'page.php';
        if (wp_get_nav_menu_object('portal-'.  strtolower($title_forschung))) {
            $menu_portal_forschung = wp_get_nav_menu_object('portal-'.  strtolower($title_forschung))->term_id;
        }
        $page_forschung = get_page_by_title($title_forschung);
        if (!$page_forschung
                || $page_forschung->post_parent != 0
                || $page_forschung->post_status != 'publish') {
            $args = array(
                'post_content' => '[cris show=fields]',
                'post_title' => $title_forschung,
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
                    'portalmenu-slug' => $menu_portal_forschung
                )
            );
            $research_pid = wp_insert_post($args);
            if ($research_pid) {
                $num_created += 1;
                $message .= '<br>- ' . sprintf( __( 'Seite %s wurde erstellt.', 'fau-cris' ), $title_forschung );
            }
        } else {
            $research_pid = $page_forschung->ID;
            $message .= '<br>- ' . sprintf( __( 'Seite "%s" existiert bereits.', 'fau-cris' ), $title_forschung );
            $num_updated += 1;
        }

        // Seiten Forschungsbereiche unter Forschung
        $page_field = array();
        $page_proj = array();
        foreach ($pages as $field_id => $field) {
            $field_pages = get_pages(array('child_of' => $research_pid, 'post_status' => 'publish'));
            foreach ($field_pages as $field_page) {
                if ($field_page->post_title == $field['title']) {
                    $page_field[] = $field_page;
                }
            }
            if (!count($page_field)) {
                $args = array(
                    'post_content' => "[cris show=fields field=$field_id hide=title]",
                    'post_title' => $field['title'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_parent' => $research_pid,
                    'menu_order' => -99,
                    'page_template' => $page_template_nav,
                );
                $field_pid = wp_insert_post($args);
                if($field_pid) {
                    $message .= '<br>- ' . sprintf( __( 'Seite "%s" wurde erstellt.', 'fau-cris' ), $field['title'] );
                    $num_created += 1;
                } else {
                    $message .= '<br>- ' . sprintf( __( 'Seite "%s" konnte nicht erstellt werden.', 'fau-cris' ), $field['title'] );
                    $num_errors += 1;
                }
            } else {
                $message .= '<br>- ' . sprintf( __( 'Seite "%s" existiert bereits.', 'fau-cris' ), $field['title'] );
                $field_pid = $page_field[0]->ID;
                $num_updated += 1;
            }

            // Seiten Forschungsprojekte innerhalb der Forschungsbereiche
            $projects = $field['projects'];
            foreach ($projects as $proj_id => $project) {
                $proj_pages = get_pages(array('child_of' => $field_pid, 'post_status' => 'publish'));
                foreach ($proj_pages as $proj_page) {
                    if ($proj_page->post_title == $project) {
                        $page_proj[] = $proj_page;
                    }
                }
                if (!count($page_proj)) {
                    $args = array(
                        'post_content' => "[cris show=projects project=$proj_id]",
                        'post_title' => $project,
                        'post_status' => 'publish',
                        'post_type' => 'page',
                        'post_parent' => $field_pid,
                        'menu_order' => -99,
                        'page_template' => $page_template_nav,
                    );
                    $proj_pid = wp_insert_post($args);
                    if($proj_pid) {
                        $message .= '<br>- ' . sprintf( __( 'Seite "%s" wurde erstellt.', 'fau-cris' ), $project );
                        $num_created += 1;
                    } else {
                        $message .= '<br>- ' . sprintf( __( 'Seite "%s" konnte nicht erstellt werden.', 'fau-cris' ), $project );
                        $num_errors += 1;
                    }
                } else {
                    $message .= '<br>- ' . sprintf( __( 'Seite "%s" existiert bereits.', 'fau-cris' ), $project );
                    $num_updated += 1;
                }
            }
        }

        $message .= '<br><br>' . sprintf( __( '%1d Seite(n) bereits vorhanden, %2d Seite(n) wurden neu erstellt.', 'fau-cris' ), $num_updated, $num_created );
        if ($num_errors > 0)
                $message .= '<br>' . sprintf( __( '%d Seite(n) konnten nicht erstellt werden.', 'fau-cris' ), $num_errors );
        // Admin-Notice: Synchronisation erfolgreich
        add_settings_error('AutoSyncComplete', 'autosynccomplete', $message , 'updated' );
        settings_errors();
    }
}
