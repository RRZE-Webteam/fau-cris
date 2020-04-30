<?php

namespace FAU\CRIS\Entities;

use FAU\CRIS\Entity;
use FAU\CRIS\Formatter;
use FAU\CRIS\Webservice;
use FAU\CRIS\Filter;
use const FAU\CRIS\DOI;
use const FAU\CRIS\BIBTEX_URL;
use const FAU\CRIS\FAU_CRIS_CLOSE;
use const FAU\CRIS\FAU_CRIS_OPEN;
use const FAU\CRIS\CRIS_PUBLICWEB;
use const FAU\CRIS\TYPEINFOS;


class Publications {

	public function __construct($parameter, $content, $tag='', $options) {
        $this->parameter = $parameter;
		$this->content = $content;
		$this->tag = $tag;
		$this->options = $options;
		$this->langdiv_open = FAU_CRIS_OPEN;
		$this->langdiv_close = FAU_CRIS_CLOSE;
		if ($parameter['display_language'] != $parameter['page_language']) {
			$this->langdiv_open = str_replace('">', '" lang="' . $parameter['display_language'] . '">', FAU_CRIS_OPEN);
		}
	}

	public function flatList() {
        $ws = new PublicationsRequest();
        try {
            $pubArray = $ws->get_publications($this->parameter);
        } catch (Exception $ex) {
            return;
        }
        if (!count($pubArray)) {
			$output = '<p>' . __('No publications found', 'fau-cris') . '</p>';
			return $output;
		}
		switch ($this->parameter['sortby']) {
			case 'created':
				$sort = 'createdon';
				$sort_order = SORT_DESC;
				break;
			case 'updated':
				$sort = 'updatedon';
				$sort_order = SORT_DESC;
				break;
			default:
				$sort = 'virtualdate';
				$sort_order = SORT_DESC;
		}
		if ($this->parameter['publication'] != '') {
			$sort = NULL;
			$sort_order = NULL;
		}
        $formatter = new Formatter(NULL, NULL, $sort, $sort_order);
		$res = $formatter->execute( $pubArray );
        if ($sort == NULL) {
			$sort = __('O.A.','fau-cris');
		}
		if ($this->parameter['limit'] != '')
			$pubList = array_slice($res[$sort], 0, $this->parameter['limit']);
		else
			$pubList = $res[$sort];
		$output = '';
        if ($this->parameter['quotation'] == 'apa' || $this->parameter['quotation'] == 'mla') {
			$output .= $this->make_quotation_list($pubList, $this->parameter['quotation']);
		} else {
			if ($this->tag == 'custom') {
				$output .= $this->make_custom_list($pubList, $this->content, $this->parameter['display_language']);
			} else {
				$output .= $this->make_list($pubList, 1, $this->parameter['nameorder'], $this->parameter['display_language']);
			}
		}
        return $this->langdiv_open . $output . $this->langdiv_close;
	}

	public function orderedList() {
		$ws = new PublicationsRequest();
        try {
            $pubArray = $ws->get_publications($this->parameter);
        } catch (Exception $ex) {
            var_dump($ex);
            return;
        }
		if (!count($pubArray)) {
			$output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
			return $output;
		}
		if (empty($this->parameter['order']))
			$this->parameter['order'][0] = 'year';
		$typeorder = \FAU\CRIS\getOrder('publications');
		if (in_array('type', $this->parameter['order'] )) {
			$typeorder_raw = $this->options['cris_layout_cris_pub_order'];
			$typeorder = explode("\n", str_replace("\r", "", $typeorder_raw));

			if ($typeorder[0] != '' && ((array_search($typeorder[0], array_column(TYPEINFOS['publications'], 'short')) !== false) || array_search($typeorder[0], array_column(TYPEINFOS['publications'], 'short_alt')) !== false)) {
				foreach ($typeorder as $key => $value) {
					$typeorder[$key] = \FAU\CRIS\getXType('publications', $value);
				}
			} else {
				$typeorder = getOrder('publications');
			}
		}
		$order_dict =[
			'type' => [
				'field' => 'publication type',
				'sort' => array_values($typeorder)],
			'subtype' => [
				'field' => 'subtype',
				'sort' => NULL],
			'year' => [
				'field' => 'publyear',
				'sort' => SORT_DESC],
			'virtualdate' => [
				'field' => 'virtualdate',
				'sort' => SORT_DESC],
			'author'  => [
				'field' => 'relauthors',
				'sort' => SORT_ASC
            ]
		];
		$order = $this->parameter['order'];

		if (count($order) > 1) {
			$formatter = new Formatter( $order_dict[ $order[0] ]['field'], $order_dict[ $order[0] ]['sort'], $order_dict[ $order[1] ]['field'], $order_dict[ $order[1] ]['sort'] );
		} else {
			$formatter = new Formatter( $order_dict[ $order[0] ]['field'], $order_dict[ $order[0] ]['sort'], "virtualdate", SORT_DESC);
		}
		$pubList = $formatter->execute($pubArray);

		$output = '';
		$shortcode_data = '';
		$format = $this->parameter['format'];

		foreach ($pubList as $array_type => $publications) {
			$title = \FAU\CRIS\getTitle('publications', $array_type, $this->parameter['display_language']);
			if (count($order) > 1) {
				$subformatter = new Formatter($order_dict[$order[1]]['field'], $order_dict[$order[1]]['sort'], "virtualdate", SORT_DESC);
			} else {
				$subformatter = new Formatter(NULL, NULL, "virtualdate", SORT_DESC);
			}
			$pubSubList = $subformatter->execute($publications);

			$sublists = [];
			foreach ($pubSubList as $array_subtype => $publications_sub) {
				$subtitle = '';
				if (isset($order[1]) && $order[1] == 'type') {
					$title_sub = \FAU\CRIS\getTitle('publications', $array_subtype, $this->parameter['display_language']);
				} elseif (isset($order[1]) && $order[1] == 'subtype') {
					$title_sub = \FAU\CRIS\getTitle('publications', $array_subtype, $this->parameter['display_language'], $array_type);
				} elseif (isset($order[1]) && $order[1] == 'year') {
					$title_sub = $array_subtype;
				} else {
					$title_sub = '';
				}
				if ($title_sub != '') {
					$subtitle = "<h4>" . $title_sub . "</h4>";
				}
				if ( $this->parameter['quotation'] == 'apa' || $this->parameter['quotation'] == 'mla' ) {
					$sublists[$subtitle] = $this->make_quotation_list($publications_sub);
				} else {
					if ($this->tag == 'cris-custom') {
						$sublists[$subtitle] = $this->make_custom_list($publications_sub, $this->content, $this->parameter['display_language']);
					} else {
						$sublists[ $subtitle ] = $this->make_list( $publications_sub, 0, $this->parameter['nameorder'], $this->parameter['display_language'] );
					}
				}
			}

			if ($format == 'accordion') {
				$sublist_string = '';
				foreach ($sublists as $_subtitle => $sublist) {
					$sublist_string .= $_subtitle . PHP_EOL . $sublist . PHP_EOL;
				}
				$shortcode_data .= do_shortcode('[collapse title="' . $title . '"]' . $sublist_string . '[/collapse]');
			} else {
				$output .= "<h3>" . $title ."</h3>";
				foreach ($sublists as $_subtitle => $sublist) {
					$output .= $_subtitle . PHP_EOL . $sublist . PHP_EOL;
				}
			}

		}
		if ($format == 'accordion') {
			$output .= do_shortcode('[collapsibles expand-all-link="true"]' . $shortcode_data . '[/collapsibles]');
		}

		return $this->langdiv_open . $output . $this->langdiv_close;
	}


	/* =========================================================================
     * Private Functions
     * ========================================================================= */

	/*
     * Ausgabe der Publikationsdetails, unterschiedlich nach Publikationstyp
     */

	private function make_list($publications, $showsubtype = false) {

	    $list_class = ($this->parameter['format'] == 'no-list' ? 'no-list' : '');
        $publist = "<ul class=\"cris-publications $list_class\">";

        foreach ($publications as $publicationObject) {
			$publication = $publicationObject->attributes;
			// id
			$id = $publicationObject->ID;
			// authors
			if (strtolower($publication['complete author relations']) == 'yes') {
				$authors = explode("|", $publication['exportauthors']);
				$authorIDs = explode(",", $publication['relpersid']);
				$authorsArray = array();
				foreach ($authorIDs as $i => $key) {
					$nameparts = explode(":", $authors[$i]);
					$authorsArray[] = array(
						'id' => $key,
						'lastname' => $nameparts[0],
						'firstname' => $nameparts[1]);
				}
				$authorList = array();
				foreach ($authorsArray as $author) {
					$authorList[] = \FAU\CRIS\getPersonLink($author['id'], $author['firstname'], $author['lastname'], $this->options['cris_layout_cris_univis'], 1, 1, $this->parameter['nameorder']);
				}
				$authors_html = implode(", ", $authorList);
			} else {
				if ($publication['publication type'] == "Editorial") {
					$authors_html = $publication['srceditors'];
				} else {
					$authors_html = $publication['srcauthors'];
				}
			}
			// title (bei Rezensionen mit Original-Autor davor)
			$title = '';
			if (($publication['publication type'] == 'Translation' || $publication['subtype'] == 'Rezension') && $publication['originalauthors'] != '') {
				$title = strip_tags($publication['originalauthors']) . ': ';
			}
			$title .= (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris'));
			global $post;
			$title_html = "<span class=\"title\" itemprop=\"name\"><strong>"
			              //. "<a href=\"" . getItemUrl("publication", $title, $id, $post->ID) . "\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
			              . $title
			              . "</a></strong></span>";
			if ($publication['openaccess'] == "Ja") {
				$title_html .= "<span aria-hidden class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>";
			}
			// make array
			$pubDetails = array(
				'id' => $id,
				'authors' => $authors_html,
				'title' => $title_html,
				'city' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
				'publisher' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
				'year' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
				'pubType' => (array_key_exists('futurepublicationtype', $publication) && $publication['futurepublicationtype'] != '') ? strip_tags($publication['futurepublicationtype']) : (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris')),
				'pubStatus' => (array_key_exists('publstatus', $publication) ? strip_tags($publication['publstatus']) : ''),
				'pagesTotal' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
				'pagesRange' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
				'lexiconColumn' => (array_key_exists('lexiconcolumn', $publication) ? strip_tags($publication['lexiconcolumn']) : ''),
				'volume' => (array_key_exists('cfvol', $publication) ? strip_tags($publication['cfvol']) : __('O.A.', 'fau-cris')),
				'series' => (array_key_exists('cfseries', $publication) ? strip_tags($publication['cfseries']) : __('O.A.', 'fau-cris')),
				'seriesNumber' => !empty($publication['book volume']) ? strip_tags($publication['book volume']) : '',
				'ISBN' => (array_key_exists('cfisbn', $publication) ? strip_tags($publication['cfisbn']) : __('O.A.', 'fau-cris')),
				'ISSN' => (array_key_exists('cfissn', $publication) ? strip_tags($publication['cfissn']) : __('O.A.', 'fau-cris')),
				'DOI' => (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : __('O.A.', 'fau-cris')),
				'OA' => (array_key_exists('openaccess', $publication) ? strip_tags($publication['openaccess']) : false),
				'OAlink' => (array_key_exists('openaccesslink', $publication) ? strip_tags($publication['openaccesslink']) : ''),
				'URI' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
				'editiors' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : __('O.A.', 'fau-cris')),
				'booktitle' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : __('O.A.', 'fau-cris')), // Titel des Sammelbands
				'journaltitle' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : __('O.A.', 'fau-cris')),
				'eventtitle' => (array_key_exists('event title', $publication) ? strip_tags($publication['event title']) : ''),
				'eventlocation' => (array_key_exists('event location', $publication) ? strip_tags($publication['event location']) : ''),
				'eventstart_raw' => !empty($publication['event start date']) ? $publication['event start date'] : (!empty($publication['publyear']) ? $publication['publyear'] : '-----'),
				'eventend_raw' => (!empty($publication['event end date']) ? $publication['event end date'] : ''),
				'eventstart' => !empty($publication['event start date']) ? date_i18n( get_option( 'date_format' ), strtotime(strip_tags($publication['event start date']))) : '',
				'eventend' => (!empty($publication['event end date']) ? date_i18n( get_option( 'date_format' ), strtotime(strip_tags($publication['event end date']))) : ''),
				'origTitle' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : __('O.A.', 'fau-cris')),
				'language' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : __('O.A.', 'fau-cris')),
				'bibtex_link' => '<a href="' . sprintf(BIBTEX_URL, $id) . '">Download</a>',
				'otherSubtype' => (array_key_exists('type other subtype', $publication) ? $publication['type other subtype'] : ''),
				'thesisSubtype' => (array_key_exists('publication thesis subtype', $publication) ? $publication['publication thesis subtype'] : ''),
				'articleNumber' => (array_key_exists('article number', $publication) ? $publication['article number'] : ''),
				'conferenceProceedingsTitle' => (array_key_exists('conference proceedings title', $publication) ? $publication['conference proceedings title'] : '')
			);

            $publication['images'] = [];
            if ($this->parameter['showimage'] == 1 || ($this->parameter['publication'] != '' && $this->parameter['showimage'] != '0')) {
                $imgs = new Images($this->parameter);
                $publication['images'] = $imgs->get_images('publication', $id, $this->parameter['image_align']);
            }
            $imgclear = (count($publication['images']) > 0 ? '<div style="float: none; clear: both;"></div>' : '');

			switch (strtolower($pubDetails['pubType'])) {
				case "book": // OK
					$publist .= "<li class=\"cris-publication\" itemscope itemtype=\"http://schema.org/Book\">";
                    if($this->parameter['image_position'] == 'top') {
                        $publist .= implode ('', $publication['images']);
                    }
                    $publist .= $pubDetails['authors'] . ':'
                        . "<br />" . $pubDetails['title']
                        . ($publication['publication type'] == 'Unpublished' ? ' (' . \FAU\CRIS\getName('publications', $publication['publication type'], $this->parameter['display_language'], $pubDetails['pubType']) . ')' : '')
                        . ((($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '')
                        . ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
                    if (!empty($pubDetails['publisher'])) {
						$publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">"
                            . ($pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\"><span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '')
                            . "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
					} else {
						$publist .= ($pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '');
					}
					$publist .= ($pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '');
					if (!empty($pubDetails['series'])) {
						$publist .= ($pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '')
                            . ($pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '')
                            . ")";
					}
					$publist .= ($pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '')
                        . ($pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '')
                        . ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . DOI . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '')
                        . ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '' ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '')
                        . ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "other":
				case "article in edited volumes":
				    if (($pubDetails['pubType'] == 'Other' && $pubDetails['booktitle'] != '') || $pubDetails['pubType'] == 'Article in Edited Volumes') {
						$publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                        if($this->parameter['image_position'] == 'top') {
                            $publist .= implode ('', $publication['images']);
                        }
                        $publist .= $pubDetails['authors'] . ':'
                            . "<br />" . $pubDetails['title'];
						if ($pubDetails['booktitle'] != '') {
						    $publist .= "<br /><span itemscope itemtype=\"http://schema.org/Book\">In: "
                                . ($pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '')
                                . "<span itemprop=\"name\"><strong>" . $pubDetails['booktitle'] . "</strong></span>"
                                . (($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '')
                                . ($pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\"><span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '')
                                . ($pubDetails['publisher'] != '' ? "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span>, " : '')
                                . (($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? "</span>" : '')
                                . ($pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '');
							if ($pubDetails['pagesRange'] != '') {
								$publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
							} elseif ($pubDetails['articleNumber'] != '') {
								$publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
							}
							$publist .= ($pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '');
							if (!empty($pubDetails['series'])) {
								$publist .= ($pubDetails['series'] != '' ? " (" . $pubDetails['series'] : '')
                                    . ($pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '')
                                    . ")";
							}
							$publist .= ($pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '')
                                . "</span>";
						}
						$publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . DOI . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a></span>" : '')
                            . (($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '')
                            . ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '');
						break;
					}
				case "journal article":
					$publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    if($this->parameter['image_position'] == 'top') {
                        $publist .= implode ('', $publication['images']);
                    }
                    $publist .= $pubDetails['authors'] . ":"
                        . "<br />" . $pubDetails['title']
                        . (($pubDetails['journaltitle'] != '' || $pubDetails['volume'] != '' || $pubDetails['year'] != '' || $pubDetails['pagesRange'] != '') ? "<br />" : '')
                        . ($pubDetails['journaltitle'] != '' ? "In: <span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"name\"><strong>" . $pubDetails['journaltitle'] . "</strong></span></span>" : '')
                        . ($pubDetails['seriesNumber'] != '' ? " <span itemprop=\"isPartOf\" itemscope itemtype=\"http://schema.org/PublicationVolume\"><link itemprop=\"isPartOf\" href=\"#periodical_" . $pubDetails['id'] . "\" /><span itemprop=\"volumeNumber\">" . $pubDetails['seriesNumber'] . "</span></span> " : '')
					    . ($pubDetails['year'] != '' ? " (<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)" : '');
					if ($pubDetails['pagesRange'] != '') {
						$publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
					} elseif ($pubDetails['articleNumber'] != '') {
						$publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
					}
					$publist .= ($pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '')
					    . ($pubDetails['ISSN'] != '' ? "<br><span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span></span></span>" : "</span>")
					    . ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . DOI . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '')
					    . (($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '')
					    . ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "conference contribution": // OK
					$publist .= "<li itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
                    if($this->parameter['image_position'] == 'top') {
                        $publist .= implode ('', $publication['images']);
                    }
                    $publist .= $pubDetails['authors'] . ':'
                        . "<br />" . $pubDetails['title']
                        . ($publication['publication type'] == 'Unpublished' ? ' (' . \FAU\CRIS\getName('publications', $publication['publication type'], $this->parameter['display_language'], $pubDetails['pubType']) . (!empty($pubDetails['pubStatus']) ? ', ' . strtolower($pubDetails['pubStatus']) : '') . ')' : '');
					if ($pubDetails['eventtitle'] != '') {
						$publist .= "<br /><span itemscope itemtype=\"http://schema.org/Event\" style=\"font-style:italic;\">"
                            . "<span itemprop=\"name\">" . $pubDetails['eventtitle'] . "</span>"
                            . (($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? " (" : '')
                            . ($pubDetails['eventlocation'] != '' ? "<span itemprop =\"location\" itemscope itemtype=\"http://schema.org/PostalAddress\">"		                                                 . "<span itemprop=\"name\">" . $pubDetails['eventlocation'] . "</span></span>" : '')
                            . ($pubDetails['eventstart'] != '' ? ", <span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\">" . $pubDetails['eventstart'] . "</span>" : "<span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\"></span>")
                            . ($pubDetails['eventend'] != '' ? " - <span itemprop=\"endDate\" content=\"" . $pubDetails['eventend_raw'] . "\">" . $pubDetails['eventend'] . "</span>" : '')
                            . (($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? ")" : '')
                            . "</span>";
					}
					if ($pubDetails['conferenceProceedingsTitle'] != '') {
						$publist .= "<br /><span itemscope itemtype=\"http://schema.org/Book\">In: "
                            . ($pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '')
                            . "<span itemprop=\"name\" style=\"font-weight:bold;\">" . $pubDetails['conferenceProceedingsTitle'] . "</span>"
                            . ($pubDetails['city'] != '' ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '')
                            . ($pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\"><span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '')
                            . ($pubDetails['city'] != '' ? "</span>" : '')
                            . ($pubDetails['year'] != '' ? " <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '')
                            . "</span>";
					}
					$publist .= ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . DOI . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '')
                        . (($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '')
                        . ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "editorial":
				case "edited volumes":
					$publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    if($this->parameter['image_position'] == 'top') {
                        $publist .= implode ('', $publication['images']);
                    }
                    $publist .= $pubDetails['authors'] . ' (' . __('Hrsg.', 'fau-cris') . '):'
                        . "<br />" . $pubDetails['title']
                        . ($pubDetails['volume'] != '' ? "<br /><span itemprop=\"volumeNumber\">" . $pubDetails['volume'] . "</span>. " : '');
					if (!empty($pubDetails['publisher'])) {
						$publist .= "<br /><span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
					}
					if (!empty( $pubDetails['city'])) {
						if (empty($pubDetails['publisher'])) {
							$publist .= "<br />";
						}
						$publist .= "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">" . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: ";
					}
					if($pubDetails['year'] != '') {
						if (empty($pubDetails['publisher']) && empty($pubDetails['city'])) {
							$publist .= "<br />";
						}
						$publist .= "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>";
					}
					if (!empty($pubDetails['series'])) {
						$publist .= ($pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '')
                            . ($pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . " " . $pubDetails['seriesNumber'] : '')
                            . ")";
					}
					$publist .= ($pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '')
                        . ($pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '')
                        . ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . DOI . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '')
                        . (($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '')
                        . ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "thesis":
					$publist .= "<li itemscope itemtype=\"http://schema.org/Thesis\">";
                    if($this->parameter['image_position'] == 'top') {
                        $publist .= implode ('', $publication['images']);
                    }
                    $publist .= $pubDetails['authors'] . ':'
                        . "<br />" . $pubDetails['title']
                        . " (" . ($pubDetails['thesisSubtype'] != '' ? \FAU\CRIS\getName('publications', 'Thesis', $this->parameter['display_language'], $pubDetails['thesisSubtype']) : __('Abschlussarbeit', 'fau-cris')) . ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)"
                        . ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . DOI . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '')
                        . ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '');
					break;
				case "translation":
					$publist .= "<li itemscope itemtype=\"http://schema.org/Book\">";
                    if($this->parameter['image_position'] == 'top') {
                        $publist .= implode ('', $publication['images']);
                    }
                    $publist .= $pubDetails['authors'] . ':'
                        . $pubDetails['title']
                        . (($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '') ? "<br />" : '')
                        . ($pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '');
					if (!empty($pubDetails['publisher'])) {
						$publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">"
                            . ($pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\"><span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '')
                            . "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
					} else {
						$publist .= ($pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '');
					}
					$publist .= ($pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '')
                        . ($pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '')
                        . ($pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '')
                        . ($pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '')
                        . ($pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '')
                        . ($pubDetails['ISSN'] != '' ? "<br /><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span>" : '')
                        . ($pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . DOI . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '')
                        . (($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '')
                        . ($pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '')
                        . ($pubDetails['origTitle'] != '' ? "<br />Originaltitel: " . $pubDetails['origTitle'] : '')
                        . ($pubDetails['language'] != '' ? "<br />Sprache: <span itemprop=\"inLanguage\">" . $pubDetails['language'] . "</span>" : '');
					break;
			}
			if ($this->options['cris_layout_cris_bibtex'] == 1) {
				$publist .= "<br />BibTeX: " . $pubDetails['bibtex_link'];
			}
			if ($showsubtype == 1 && $pubDetails['otherSubtype'] != '') {
				$publist .= "<br />(" . $pubDetails['otherSubtype'] . ")";
			}
            if($this->parameter['image_position'] == 'bottom') {
                $publist .= $publication['images'];
            }
			$publist .= $imgclear . '</li>';
		}
		$publist .= "</ul>";

		return $publist;
	}

	/*
	 * Ausgabe der Publikationsdetails in Zitierweise (MLA/APA)
	 */

	private function make_quotation_list($publications, $lang = 'de', $single = false) {
		$tools = new Tools();
		$quotation = strtolower($this->parameter['quotation']);
		if (!$single) {
			$publist = "<ul class=\"cris-publications\" lang=\"" . $lang . "\">";
			$item_wrap = 'li';
		} else {
			$publist = '';
			$item_wrap = 'div';
		}
		foreach ($publications as $publication) {
			$publication->insert_quotation_links();
			$publist .= "<$item_wrap>";
			$publist .= $publication->attributes['quotation' . $quotation . 'link'];
			if ($publication->attributes['openaccess'] == "Ja" && isset($this->options['cris_oa']) && $this->options['cris_oa'] == 1) {
				$publist .= "<span aria-hidden class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>";
			}
			if (isset($this->options['cris_doi']) && $this->options['cris_doi'] == 1 && !empty($publication->attributes['doi'])) {
				$publist .= "<br />DOI: <a href='" . DOI . $publication->attributes['doi'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['doi'] . "</a>";
			}
			if (isset($this->options['cris_url']) && $this->options['cris_url'] == 1 && !empty($publication->attributes['cfuri'])) {
				$publist .= "<br />URL: <a href='" . $publication->attributes['cfuri'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['cfuri'] . "</a>";
			}
			if (isset($this->options['cris_bibtex']) && $this->options['cris_bibtex'] == 1) {
				$publist .= '<br />BibTeX: <a href="' . sprintf($this->bibtexlink, $publication->attributes['id_publ']) . '">Download</a>';
			}
			$publist .= "</$item_wrap>";
		}
		if (!$single) {
			$publist .= "</ul>";
		}
		return $publist;
	}

	/*
	 * Personalisierte Ausgabe der Publikationsdetails
	 */

	private function make_custom_list($publications, $custom_text, $lang = 'de') {
		$publist = '';
		$list = (count($publications) > 1) ? true : false;
		if ($list) {
			$publist .= "<ul class=\"cris-publications\" lang=\"" . $lang . "\">";
		} else {
			$publist .= "<div class=\"cris-publications\" lang=\"" . $lang . "\">";
		}
		$tools = new Tools();
		foreach ($publications as $publObject) {
			$publication = (array) $publObject;
			foreach ($publication['attributes'] as $attribut => $v) {
				$publication[$attribut] = $v;
			}
			unset($publication['attributes']);
			$id = $publication['ID'];
			// authors
			if (strtolower($publication['complete author relations']) == 'yes') {
				$authors = explode("|", $publication['exportauthors']);
				$authorIDs = explode(",", $publication['relpersid']);
				$authorsArray = array();
				foreach ($authorIDs as $i => $key) {
					$nameparts = explode(":", $authors[$i]);
					$authorsArray[] = array(
						'id' => $key,
						'lastname' => $nameparts[0],
						'firstname' => $nameparts[1]);
				}
				$authorList = array();
				foreach ($authorsArray as $author) {
					$authorList[] = \FAU\CRIS\getPersonLink($author['id'], $author['firstname'], $author['lastname'], $this->options['cris_layout_cris_univis'],1, 1, $this->parameter['nameorder']);
				}
				$authors_html = implode(", ", $authorList);
			} else {
				if ($publication['publication type'] == "Editorial") {
					$authors_html = $publication['srceditors'];
				} else {
					$authors_html = $publication['srcauthors'];
				}
			}
			// title (bei Rezensionen mit Original-Autor davor)
			$title = '';
			if (($publication['publication type'] == 'Translation' || $publication['subtype'] == 'Rezension') && $publication['originalauthors'] != '') {
				$title = strip_tags($publication['originalauthors']) . ': ';
			}
			$title .= (array_key_exists('cftitle', $publication) ? strip_tags($publication['cftitle']) : __('O.T.', 'fau-cris'));
			global $post;
			$title_html = "<span class=\"title\" itemprop=\"name\"><strong>"
			              . "<a href=\"" . \FAU\CRIS\getItemUrl("publication", $title, $id, $post->ID) . "\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
			              . $title
			              . "</a></strong></span>";
			//pubType
			$pubTypeRaw = (array_key_exists('futurepublicationtype', $publication) && $publication['futurepublicationtype'] != '') ? strip_tags($publication['futurepublicationtype']) : (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris'));
			$pubType = \FAU\CRIS\getName('publications', $pubTypeRaw, $this->parameter['display_language']);
			// make array
			setlocale(LC_TIME, get_locale());
			$pubDetails = array(
				'#id#' => $id,
				'#author#' => $authors_html,
				'#title#' => $title,
				'#url#' => \FAU\CRIS\getItemUrl("publication", $title, $id, $post->ID),
				'#city#' => (array_key_exists('cfcitytown', $publication) ? strip_tags($publication['cfcitytown']) : __('O.O.', 'fau-cris')),
				'#publisher#' => (array_key_exists('publisher', $publication) ? strip_tags($publication['publisher']) : __('O.A.', 'fau-cris')),
				'#year#' => (array_key_exists('publyear', $publication) ? strip_tags($publication['publyear']) : __('O.J.', 'fau-cris')),
				'#pubType#' => $pubType,
				'#pubStatus#' => (array_key_exists('publstatus', $publication) ? strip_tags($publication['publstatus']) : ''),
				'#pagesTotal#' => (array_key_exists('cftotalpages', $publication) ? strip_tags($publication['cftotalpages']) : ''),
				'#pagesRange#' => (array_key_exists('pagesrange', $publication) ? strip_tags($publication['pagesrange']) : ''),
				'#lexiconColumn#' => (array_key_exists('lexiconcolumn', $publication) ? strip_tags($publication['lexiconcolumn']) : ''),
				'#volume#' => (array_key_exists('cfvol', $publication) ? strip_tags($publication['cfvol']) : __('O.A.', 'fau-cris')),
				'#series#' => (array_key_exists('cfseries', $publication) ? strip_tags($publication['cfseries']) : __('O.A.', 'fau-cris')),
				'#seriesNumber#' => !empty($publication['book volume']) ? strip_tags($publication['book volume']) : '',
				'#ISBN#' => (array_key_exists('cfisbn', $publication) ? strip_tags($publication['cfisbn']) : __('O.A.', 'fau-cris')),
				'#ISSN#' => (array_key_exists('cfissn', $publication) ? strip_tags($publication['cfissn']) : __('O.A.', 'fau-cris')),
				'#DOI#' => (array_key_exists('doi', $publication) ? strip_tags($publication['doi']) : __('O.A.', 'fau-cris')),
				'#URI#' => (array_key_exists('cfuri', $publication) ? strip_tags($publication['cfuri']) : __('O.A.', 'fau-cris')),
				'#editors#' => (array_key_exists('editor', $publication) ? strip_tags($publication['editor']) : __('O.A.', 'fau-cris')),
				'#bookTitle#' => (array_key_exists('edited volumes', $publication) ? strip_tags($publication['edited volumes']) : __('O.A.', 'fau-cris')), // Titel des Sammelbands
				'#journalTitle#' => (array_key_exists('journalname', $publication) ? strip_tags($publication['journalname']) : __('O.A.', 'fau-cris')),
				'#eventTitle#' => (array_key_exists('event title', $publication) ? strip_tags($publication['event title']) : ''),
				'#eventLocation#' => (array_key_exists('event location', $publication) ? strip_tags($publication['event location']) : ''),
				'#eventstart_raw#' => !empty($publication['event start date']) ? $publication['event start date'] : (!empty($publication['publyear']) ? $publication['publyear'] : '-----'),
				'#eventend_raw#' => (!empty($publication['event end date']) ? $publication['event end date'] : ''),
				'#eventStart#' => !empty($publication['event start date']) ? date_i18n( get_option( 'date_format' ), strtotime(strip_tags($publication['event start date']))) : '',
				'#eventEnd#' => (!empty($publication['event end date']) ? date_i18n( get_option( 'date_format' ), strtotime(strip_tags($publication['event end date']))) : ''),
				'#originalTitle#' => (array_key_exists('originaltitel', $publication) ? strip_tags($publication['originaltitel']) : __('O.A.', 'fau-cris')),
				'#language#' => (array_key_exists('language', $publication) ? strip_tags($publication['language']) : __('O.A.', 'fau-cris')),
				'#bibtexLink#' => '<a href="' . sprintf(BIBTEX_URL, $id) . '">Download</a>',
				'#subtype#' => (array_key_exists('subtype', $publication) ? $publication['subtype'] : ''),
				'#articleNumber#' => (array_key_exists('article number', $publication) ? $publication['article number'] : ''),
				'#projectTitle#' => '',
				'#projectLink#' => '',
				'#oaIcon#' => ($publication['openaccess'] == "Ja") ? "<span aria-hidden class=\"oa-icon\" title=\"Open-Access-Publikation\"></span>" : '',
			);
			if (strpos($custom_text, '#projectTitle#' ) !== false) {
	//			$pubDetails['#projectTitle#'] = $this->get_pub_projects($id, 'title');
			}
			if (strpos($custom_text, '#projectLink#' ) !== false) {
	//			$pubDetails['#projectLink#'] = $this->get_pub_projects($id, 'link');
			}
			if ($list) {
				$publist .= "<li>"; }
			$publist .= strtr($custom_text, $pubDetails);
			if ($list) {
				$publist .= "</li>"; }
		}
		if ($list) {
			$publist .= "</ul>";
		} else {
			$publist .= "</div>";
		}
		return $publist;
	}

	private function get_pub_projects($publication = NULL, $item = 'title') {
		$liste = new Projekte();
		$projects = $liste->pubProjects($publication);
		return $projects[$item];
	}

}

class PublicationsRequest extends Webservice {


    public function get_publications($parameter) {
        $filter = NULL;
        $filter = $this->make_filter($parameter);
        $requests = $this->make_request($parameter['show'], $parameter['entity'], $parameter['entity_id']);
var_dump($requests);
        return $this->retrieve($requests, $filter);
    }

    private function make_filter($parameter) {
        $filter = array();
        if ( $parameter['year'] !== '' ) {
            $filter['publyear__eq'] = $parameter['year'];
        }
        if ( $parameter['start'] !== '' ) {
            $filter['publyear__ge'] = $parameter['start'];
        }
        if ( $parameter['end'] !== '' ) {
            $filter['publyear__le'] = $parameter['end'];
        }
        if ( $parameter['type'] !== '' ) {
            if ( strpos( $parameter['type'], ',' ) ) {
                $type  = str_replace( ' ', '', $parameter['type'] );
                $types = explode( ',', $type );
                foreach ( $types as $v ) {
                    $pubTyp[] = getType( 'publications', $v );
                }
            } else {
                $pubTyp = (array) getType( 'publications', $parameter['type'] );
            }
            if ( empty( $pubTyp ) ) {
                $output = '<p>' . __( 'Falscher Parameter für Publikationstyp', 'fau-cris' ) . '</p>';

                return $output;
            }
            $filter['publication type__eq'] = $pubTyp;
        }
        if ( $parameter['subtype'] !== '' ) {
            $subtype  = str_replace( ' ', '', $parameter['subtype'] );
            $subtypes = explode( ',', $subtype );
            foreach ( $subtypes as $v ) {
                $pubSubTyp[] = getType( 'publications', $v, $pubTyp[0] );
            }
            if ( empty( $pubSubTyp ) ) {
                $output = '<p>' . __( 'Falscher Parameter für Publikationssubtyp', 'fau-cris' ) . '</p>';

                return $output;
            }
            $filter['subtype__eq'] = $pubSubTyp;
        }
        if ( $parameter['fau'] !== '' ) {
            if ( $parameter['fau'] == 1 ) {
                $filter['fau publikation__eq'] = 'yes';
            } elseif ( $parameter['fau'] == 0 ) {
                $filter['fau publikation__eq'] = 'no';
            }
        }
        if ( $parameter['peerreviewed'] !== '' ) {
            if ( $parameter['peerreviewed'] == 1 ) {
                $filter['peerreviewed__eq'] = 'Yes';
            } elseif ( $parameter['peerreviewed'] == 0 ) {
                $filter['peerreviewed__eq'] = 'No';
            }
        }
        if ( $parameter['language'] !== '' ) {
            $language               = str_replace( ' ', '', $parameter['language'] );
            $pubLanguages           = explode( ',', $language );
            $filter['language__eq'] = $pubLanguages;
        }
        if ( $parameter['curation'] == 1 ) {
            $filter['relation curationsetting__eq'] = 'curation_accepted';
        }
        if ( count( $filter ) ) {
            return $filter;
        }

        return null;
    }

	private function retrieve($reqs, &$filter = null) {
		if ($filter !== null && !$filter instanceof Filter) {
			$filter = new Filter($filter);
		}
		var_dump($filter);
		$data = array();
		foreach ($reqs as $_i) {
			try {
				$data[] = $this->get($_i, $filter);
			} catch (Exception $e) {
				// TODO: logging?
                //$e->getMessage();
				continue;
			}
		}
		$publs = array();
		foreach ($data as $_d) {
			foreach ($_d as $publ) {
				$p = new Publication($publ);
				if ($p->ID && ($filter === null || $filter->evaluate($p)))
					$publs[$p->ID] = $p;
			}
		}
		return $publs;
	}

}

class Publication extends Entity {
	/*
	 * object for single publication
	 */
	public function __construct($data) {
		parent::__construct($data);
	}
	public function insert_quotation_links() {
		/*
		 * Enrich APA/MLA quotation by links to publication details (CRIS
		 * website) and DOI (if present, applies only to APA).
		 */
		$tools = new Tools();
		$doilink = preg_quote("https://dx.doi.org/", "/");
		$title = preg_quote(\FAU\CRIS\numeric_xml_encode($this->attributes["cftitle"]), "/");
		$cristmpl = '<a href="' . CRIS_PUBLICWEB . 'publication/%d" target="_blank">%s</a>';
		$apa = $this->attributes["quotationapa"];
		$mla = $this->attributes["quotationmla"];
		$matches = array();
		$splitapa = preg_match("/^(.+)(" . $title . ")(.+)(" . $doilink . ".+)?$/Uu", $apa, $matches);
		if ($splitapa === 1 && isset($matches[2])) {
			$apalink = $matches[1] . \
					sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
			if (isset($matches[4]))
				$apalink .= sprintf('<a href="%s" target="_blank">%s</a>', $matches[4], $matches[4]);
		} else {
			// try to identify DOI at least
			$splitapa = preg_match("/^(.+)(" . $doilink . ".+)?$/Uu", $apa, $matches);
			if ($splitapa === 1 && isset($matches[2])) {
				$apalink = $matches[1] . \
						sprintf('<a href="%s" target="_blank">%s</a>', $matches[2], $matches[2]);
			} else
				$apalink = $apa;
		}
		$this->attributes["quotationapalink"] = $apalink;
		$matches = array();
		$splitmla = preg_match("/^(.+)(" . $title . ")(.+)$/", $mla, $matches);
		if ($splitmla === 1) {
			$mlalink = $matches[1] . \
					sprintf($cristmpl, $this->ID, $matches[2]) . $matches[3];
		} else {
			$mlalink = $mla;
		}
		$this->attributes["quotationmlalink"] = $mlalink;
	}
}