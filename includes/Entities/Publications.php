<?php

namespace FAU\CRIS\Entities;

use FAU\CRIS\Entity;
use FAU\CRIS\Formatter;
use FAU\CRIS\Webservice;
use FAU\CRIS\Tools;
use const FAU\CRIS\FAU_CRIS_CLOSE;
use const FAU\CRIS\FAU_CRIS_OPEN;


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

	public function singlePublication() {

		$ws = new PublicationsRequest();
		try {
			$pubArray = $ws->by_id($this->parameter['publication']);
		} catch (Exception $ex) {
			//var_dump($ex);
			return;
		}

		if (!count($pubArray))
			return;

		if (in_array($this->parameter['quotation'],['apa', 'mla'])) {
			$output = $this->make_quotation_list($pubArray, $this->parameter['quotation'], true);
		} else {
			if ($this->tag == 'cris-custom') {
				$output = $this->make_custom_list($pubArray, $this->content, $this->parameter['display_language'], true);
			} else {
				$output = $this->make_list($pubArray, 0, $this->parameter['nameorder'], $this->parameter['display_language'], true);
			}
		}

		return $this->langdiv_open . $output . $this->langdiv_close;
	}

	public function flatList() {
		$pubArray = $this->fetch_publications();
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
		$tools = new Tools();
		$pubArray = $this->fetch_publications();
		if (!count($pubArray)) {
			$output = '<p>' . __('Es wurden leider keine Publikationen gefunden.', 'fau-cris') . '</p>';
			return $output;
		}
		if (empty($this->parameter['order']))
			$this->parameter['order'][0] = 'year';
		$typeorder = $tools->getOrder('publications');
		if (in_array('type', $this->parameter['order'] )) {
			$typeorder_raw = $this->options['cris_layout_cris_pub_order'];
			$typeorder = explode("\n", str_replace("\r", "", $typeorder_raw));

			if ($typeorder[0] != '' && ((array_search($typeorder[0], array_column($tools->typeinfos['publications'], 'short')) !== false) || array_search($typeorder[0], array_column($tools->typeinfos['publications'], 'short_alt')) !== false)) {
				foreach ($typeorder as $key => $value) {
					$typeorder[$key] = $tools->getXType('publications', $value);
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
				'sort' => SORT_ASC]
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
			$title = $tools->getTitle('publications', $array_type, $this->parameter['display_language']);
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
					$title_sub = $tools->getTitle('publications', $array_subtype, $this->parameter['display_language']);
				} elseif (isset($order[1]) && $order[1] == 'subtype') {
					$title_sub = $tools->getTitle('publications', $array_subtype, $this->parameter['display_language'], $array_type);
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
     * Holt Daten vom Webservice je nach definierter Einheit.
     */

	private function fetch_publications() {
		$filter = NULL;
		$tools = new Tools();
		$filter = $tools->publication_filter($this->parameter);
		$ws = new PublicationsRequest();

		try {
			switch ($this->parameter['entity']) {
				case "orga":
					$pubArray = $ws->by_orga_id($this->parameter['entity_id'], $filter);
					break;
				case "person":
					$pubArray = $ws->by_pers_id($this->parameter['entity_id'], $filter, $this->parameter['notable']);
					break;
				case "project":
					$pubArray = $ws->by_project($this->parameter['entity_id'], $filter, $this->parameter['notable']);
					break;
				case "field":
				case "field_proj":
					$pubArray = $ws->by_field($this->parameter['field'], $filter, $fsp, $this->parameter['entity']);
					break;
				case "publication":
				default:
					$pubArray = $ws->by_id($this->parameter['entity_id']);
					break;
			}
		} catch (Exception $ex) {
			$pubArray = array();
		}
		return $pubArray;
	}

	/*
     * Ausgabe der Publikationsdetails, unterschiedlich nach Publikationstyp
     */

	private function make_list($publications, $showsubtype = 0, $nameorder = '', $lang = 'de', $single = false) {
		$tools = new Tools();
		if (!$single) {
			$publist = "<ul class=\"publications\" lang=\"" . $lang . "\">";
			$item_wrap = 'li';
		} else {
			$publist = '';
			$item_wrap = 'div';
		}
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
					$authorList[] = $tools->getPersonLink($author['id'], $author['firstname'], $author['lastname'], $this->options['cris_layout_cris_univis'], 1, 1, $nameorder);
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
				'bibtex_link' => '<a href="' . sprintf($tools->bibtex_uri, $id) . '">Download</a>',
				'otherSubtype' => (array_key_exists('type other subtype', $publication) ? $publication['type other subtype'] : ''),
				'thesisSubtype' => (array_key_exists('publication thesis subtype', $publication) ? $publication['publication thesis subtype'] : ''),
				'articleNumber' => (array_key_exists('article number', $publication) ? $publication['article number'] : ''),
				'conferenceProceedingsTitle' => (array_key_exists('conference proceedings title', $publication) ? $publication['conference proceedings title'] : '')
			);
			switch (strtolower($pubDetails['pubType'])) {
				case "book": // OK
					if ($single == false) {
						$publist .= "<$item_wrap class=\"cris-publication\" itemscope itemtype=\"http://schema.org/Book\">";
					}
					$publist .= $pubDetails['authors'] . ':';
					$publist .= "<br />" . $pubDetails['title'];
					$publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $publication['publication type'], $lang, $pubDetails['pubType']) . ')' : '';
					$publist .= (($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '';
					$publist .= $pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '';
					if (!empty($pubDetails['publisher'])) {
						$publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
						$publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
						                                        . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
						$publist .= "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
					} else {
						$publist .= $pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '';
					}
					$publist .= $pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
					if (!empty($pubDetails['series'])) {
						$publist .= $pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '';
						$publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
						$publist .= ")";
					}
					$publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
					$publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
					$publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . $tools->doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
					$publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
					$publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
					break;
				case "other":
				case "article in edited volumes":
					if (($pubDetails['pubType'] == 'Other' && $pubDetails['booktitle'] != '') || $pubDetails['pubType'] == 'Article in Edited Volumes') {
						$publist .= "<$item_wrap itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
						$publist .= $pubDetails['authors'] . ':';
						$publist .= "<br />" . $pubDetails['title'];
						if ($pubDetails['booktitle'] != '') {
							$publist .= "<br /><span itemscope itemtype=\"http://schema.org/Book\">In: ";
							$publist .= $pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '';
							$publist .= "<span itemprop=\"name\"><strong>" . $pubDetails['booktitle'] . "</strong></span>";
							$publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '';
							$publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
							                                        . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
							$publist .= $pubDetails['publisher'] != '' ? "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span>" : '';
							$publist .= ($pubDetails['city'] != '' || $pubDetails['publisher'] != '') ? "</span>" : '';
							$publist .= $pubDetails['year'] != '' ? ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
							if ($pubDetails['pagesRange'] != '') {
								$publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
							} elseif ($pubDetails['articleNumber'] != '') {
								$publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
							}
							$publist .= $pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '';
							if (!empty($pubDetails['series'])) {
								$publist .= $pubDetails['series'] != '' ? " (" . $pubDetails['series'] : '';
								$publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
								$publist .= ")";
							}
							$publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
							$publist .= "</span>";
						}
						$publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . $tools->doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a></span>" : '';
						$publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
						$publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
						break;
					}
				case "journal article":
					$publist .= "<$item_wrap itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
					$publist .= $pubDetails['authors'] . ":";
					$publist .= "<br />" . $pubDetails['title'];
					$publist .= (($pubDetails['journaltitle'] != '') || ($pubDetails['volume'] != '') || ($pubDetails['year'] != '') || ($pubDetails['pagesRange'] != '')) ? "<br />" : '';
					$publist .= $pubDetails['journaltitle'] != '' ? "In: <span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"name\"><strong>" . $pubDetails['journaltitle'] . "</strong></span></span>" : '';
					$publist .= $pubDetails['seriesNumber'] != '' ? " <span itemprop=\"isPartOf\" itemscope itemtype=\"http://schema.org/PublicationVolume\"><link itemprop=\"isPartOf\" href=\"#periodical_" . $pubDetails['id'] . "\" /><span itemprop=\"volumeNumber\">" . $pubDetails['seriesNumber'] . "</span></span> " : '';
					$publist .= $pubDetails['year'] != '' ? " (<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)" : '';
					if ($pubDetails['pagesRange'] != '') {
						$publist .= ", " . _x('S.', 'Abkürzung für "Seite" bei Publikationen', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['pagesRange'] . "</span>";
					} elseif ($pubDetails['articleNumber'] != '') {
						$publist .= ", " . _x('Art.Nr.', 'Abkürzung für "Artikelnummer" bei Publikationen', 'fau-cris') . ": <span itemprop=\"pagination\">" . $pubDetails['articleNumber'] . "</span>";
					}
					$publist .= $pubDetails['lexiconColumn'] != '' ? ", " . _x('Sp.', 'Abkürzung für "Spalte" bei Lexikonartikeln', 'fau-cris') . " <span itemprop=\"pagination\">" . $pubDetails['lexiconColumn'] . "</span>" : '';
					$publist .= $pubDetails['ISSN'] != '' ? "<br><span itemscope itemtype=\"http://schema.org/Periodical\" itemid=\"#periodical_" . $pubDetails['id'] . "\"><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span></span></span>" : "</span>";
					$publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . $tools->doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
					$publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
					$publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
					break;
				case "conference contribution": // OK
					$publist .= "<$item_wrap itemscope itemtype=\"http://schema.org/ScholarlyArticle\">";
					$publist .= $pubDetails['authors'] . ':';
					$publist .= "<br />" . $pubDetails['title'];
					$publist .= $publication['publication type'] == 'Unpublished' ? ' (' . Tools::getName('publications', $publication['publication type'], $lang, $pubDetails['pubType']) . (!empty($pubDetails['pubStatus']) ? ', ' . strtolower($pubDetails['pubStatus']) : '') . ')' : '';
					if ($pubDetails['eventtitle'] != '') {
						$publist .= "<br /><span itemscope itemtype=\"http://schema.org/Event\" style=\"font-style:italic;\">";
						$publist .= "<span itemprop=\"name\">" . $pubDetails['eventtitle'] . "</span>";
						$publist .= ($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? " (" : '';
						$publist .= $pubDetails['eventlocation'] != '' ? "<span itemprop =\"location\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
						                                                 . "<span itemprop=\"name\">" . $pubDetails['eventlocation'] . "</span></span>" : '';
						$publist .= $pubDetails['eventstart'] != '' ? ", <span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\">" . $pubDetails['eventstart'] . "</span>" : "<span itemprop=\"startDate\" content=\"" . $pubDetails['eventstart_raw'] . "\"></span>";
						$publist .= $pubDetails['eventend'] != '' ? " - <span itemprop=\"endDate\" content=\"" . $pubDetails['eventend_raw'] . "\">" . $pubDetails['eventend'] . "</span>" : '';
						$publist .= ($pubDetails['eventlocation'] != '' || $pubDetails['eventstart'] != '' || $pubDetails['eventend'] != '') ? ")" : '';
						$publist .= "</span>";
					}
					if ($pubDetails['conferenceProceedingsTitle'] != '') {
						$publist .= "<br /><span itemscope itemtype=\"http://schema.org/Book\">In: ";
						$publist .= $pubDetails['editiors'] != '' ? "<span itemprop=\"author\">" . $pubDetails['editiors'] . " (" . __('Hrsg.', 'fau-cris') . "): </span>" : '';
						$publist .= "<span itemprop=\"name\" style=\"font-weight:bold;\">" . $pubDetails['conferenceProceedingsTitle'] . "</span>";
						$publist .= ($pubDetails['city'] != '') ? ", <span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">" : '';
						$publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
						                                        . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
						$publist .= ($pubDetails['city'] != '') ? "</span>" : '';
						$publist .= $pubDetails['year'] != '' ? " <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
						$publist .= "</span>";
					}
					$publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . $tools->doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
					$publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
					$publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
					break;
				case "editorial":
				case "edited volumes":
					$publist .= "<$item_wrap itemscope itemtype=\"http://schema.org/Book\">";
					$publist .= $pubDetails['authors'] . ' (' . __('Hrsg.', 'fau-cris') . '):';
					$publist .= "<br />" . $pubDetails['title'];
					$publist .= $pubDetails['volume'] != '' ? "<br /><span itemprop=\"volumeNumber\">" . $pubDetails['volume'] . "</span>. " : '';
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
						$publist .= $pubDetails['series'] != '' ? "<br />(" . $pubDetails['series'] : '';
						$publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . " " . $pubDetails['seriesNumber'] : '';
						$publist .= ")";
					}
					$publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
					$publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
					$publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . $tools->doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
					$publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
					$publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
					break;
				case "thesis":
					$publist .= "<$item_wrap itemscope itemtype=\"http://schema.org/Thesis\">";
					$publist .= $pubDetails['authors'] . ':';
					$publist .= "<br />" . $pubDetails['title'];
					$publist .= " (" . ($pubDetails['thesisSubtype'] != '' ? Tools::getName('publications', 'Thesis', $lang, $pubDetails['thesisSubtype']) : __('Abschlussarbeit', 'fau-cris')) . ", <span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>)";
					$publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . $tools->doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
					$publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
					break;
				case "translation":
					$publist .= "<$item_wrap itemscope itemtype=\"http://schema.org/Book\">";
					$publist .= $pubDetails['authors'] . ':';
					$publist .= $pubDetails['title'];
					$publist .= (($pubDetails['city'] != '') || ($pubDetails['publisher'] != '') || ($pubDetails['year'] != '')) ? "<br />" : '';
					$publist .= $pubDetails['volume'] != '' ? $pubDetails['volume'] . ". " : '';
					if (!empty($pubDetails['publisher'])) {
						$publist .= "<span itemprop=\"publisher\" itemscope itemtype=\"http://schema.org/Organization\">";
						$publist .= $pubDetails['city'] != '' ? "<span class=\"city\" itemprop=\"address\" itemscope itemtype=\"http://schema.org/PostalAddress\">"
						                                        . "<span itemprop=\"addressLocality\">" . $pubDetails['city'] . "</span></span>: " : '';
						$publist .= "<span itemprop=\"name\">" . $pubDetails['publisher'] . "</span></span>, ";
					} else {
						$publist .= $pubDetails['city'] != '' ? $pubDetails['city'] . ", " : '';
					}
					$publist .= $pubDetails['year'] != '' ? "<span itemprop=\"datePublished\">" . $pubDetails['year'] . "</span>" : '';
					$publist .= $pubDetails['series'] != '' ? "<br />" . $pubDetails['series'] : '';
					$publist .= $pubDetails['seriesNumber'] != '' ? ", " . _x('Bd.', 'Abkürzung für "Band" bei Publikationen', 'fau-cris') . $pubDetails['seriesNumber'] : '';
					$publist .= $pubDetails['pagesTotal'] != '' ? "<br /><span itemprop=\"numberOfPages\">" . $pubDetails['pagesTotal'] . "</span> " . __('Seiten', 'fau-cris') : '';
					$publist .= $pubDetails['ISBN'] != '' ? "<br /><span itemprop=\"isbn\">ISBN: " . $pubDetails['ISBN'] . "</span>" : '';
					$publist .= $pubDetails['ISSN'] != '' ? "<br /><span itemprop=\"issn\">ISSN: " . $pubDetails['ISSN'] . "</span>" : '';
					$publist .= $pubDetails['DOI'] != '' ? "<br />DOI: <a href='" . $tools->doi . $pubDetails['DOI'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['DOI'] . "</a>" : '';
					$publist .= ($pubDetails['DOI'] == '' && $pubDetails['OA'] == 'Ja' && $pubDetails['OAlink'] != '') ? "<br />Open Access: <a href='" . $pubDetails['OAlink'] . "' target='blank' itemprop=\"sameAs\">" . $pubDetails['OAlink'] . "</a>" : '';
					$publist .= $pubDetails['URI'] != '' ? "<br />URL: <a href='" . $pubDetails['URI'] . "' target='blank' itemprop=\"url\">" . $pubDetails['URI'] . "</a>" : '';
					$publist .= $pubDetails['origTitle'] != '' ? "<br />Originaltitel: " . $pubDetails['origTitle'] : '';
					$publist .= $pubDetails['language'] != '' ? "<br />Sprache: <span itemprop=\"inLanguage\">" . $pubDetails['language'] . "</span>" : '';
					break;
			}
			if ($this->options['cris_layout_cris_bibtex'] == 1) {
				$publist .= "<br />BibTeX: " . $pubDetails['bibtex_link'];
			}
			if ($showsubtype == 1 && $pubDetails['otherSubtype'] != '') {
				$publist .= "<br />(" . $pubDetails['otherSubtype'] . ")";
			}
			$publist .= "</$item_wrap>";
		}
		if (!$single) {
			$publist .= "</ul>";
		}
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
				$publist .= "<br />DOI: <a href='" . $tools->doi . $publication->attributes['doi'] . "' target='blank' itemprop=\"url\">" . $publication->attributes['doi'] . "</a>";
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
					$authorList[] = $tools->getPersonLink($author['id'], $author['firstname'], $author['lastname'], $this->options['cris_layout_cris_univis'],1, 1, $this->parameter['nameorder']);
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
			              . "<a href=\"" . $tools->getItemUrl("publication", $title, $id, $post->ID) . "\" title=\"Detailansicht in neuem Fenster &ouml;ffnen\">"
			              . $title
			              . "</a></strong></span>";
			//pubType
			$pubTypeRaw = (array_key_exists('futurepublicationtype', $publication) && $publication['futurepublicationtype'] != '') ? strip_tags($publication['futurepublicationtype']) : (array_key_exists('publication type', $publication) ? strip_tags($publication['publication type']) : __('O.A.', 'fau-cris'));
			$pubType = $tools->getName('publications', $pubTypeRaw, $lang);
			// make array
			setlocale(LC_TIME, get_locale());
			$pubDetails = array(
				'#id#' => $id,
				'#author#' => $authors_html,
				'#title#' => $title,
				'#url#' => $tools->getItemUrl("publication", $title, $id, $post->ID),
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
				'#bibtexLink#' => '<a href="' . sprintf($tools->bibtex_uri, $id) . '">Download</a>',
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
	/*
	 * publication requests, supports multiple organisation ids given as array.
	 */
	public function by_orga_id($orgaID = null, &$filter = null) {
		if ($orgaID === null || $orgaID === "0" || $orgaID === "")
			throw new Exception('Please supply valid organisation ID');
		if (!is_array($orgaID))
			$orgaID = array($orgaID);
		$requests = array();
		foreach ($orgaID as $_o) {
			$requests = array_merge($requests, array(
				sprintf("getautorelated/Organisation/%d/ORGA_2_PUBL_1", $_o),
				sprintf("getrelated/Organisation/%d/Publ_has_ORGA", $_o),
			));
		}
		return $this->retrieve($requests, $filter);
	}
	public function by_pers_id($persID = null, &$filter = null, $notable = 0) {
		if ($persID === null || $persID === "0")
			throw new Exception('Please supply valid person ID');
		if (!is_array($persID))
			$persID = array($persID);
		$requests = array();
		if ($notable == 1) {
			foreach ($persID as $_p) {
				$requests[] = sprintf('getrelated/Person/%s/PUBL_has_PERS', $_p);
			}
		} else {
			foreach ($persID as $_p) {
				$requests[] = sprintf('getautorelated/Person/%s/PERS_2_PUBL_1', $_p);
			}
		}
		return $this->retrieve($requests, $filter);
	}
	public function by_id($publID = null) {
		if ($publID === null || $publID === "0")
			throw new Exception('Please supply valid publication ID');
		if (!is_array($publID))
			$publID = array($publID);
		$requests = array();
		foreach ($publID as $_p) {
			$requests[] = sprintf('get/Publication/%d', $_p);
		}
		return $this->retrieve($requests);
	}
	public function by_project($projID = null) {
		if ($projID === null || $projID === "0")
			throw new Exception('Please supply valid publication ID');
		if (!is_array($projID))
			$projID = array($projID);
		$requests = array();
		foreach ($projID as $_p) {
			$requests[] = sprintf('getrelated/Project/%d/proj_has_publ', $_p);
		}
		return $this->retrieve($requests);
	}
	public function by_field($fieldID = null, &$filter = null, $fsp = false, $entity = 'field') {
		if ($fieldID === null || $fieldID === "0")
			throw new Exception('Please supply valid research field ID');
		if (!is_array($fieldID))
			$fieldID = array($fieldID);
		$requests = array();
		switch ($entity) {
			case 'field_proj':
				$relation = $fsp ? 'fsp_proj_publ' : 'fobe_proj_publ';
				break;
			case 'field_notable':
				$relation = 'FOBE_has_cur_PUBL';
				break;
			case 'field':
			default:
				$relation = $fsp ? 'FOBE_FSP_has_PUBL' : 'fobe_has_top_publ';
		}
		foreach ($fieldID as $_p) {
			$requests[] = sprintf('getrelated/Forschungsbereich/%d/', $_p) . $relation;
		}
		return $this->retrieve($requests, $filter);
	}
	public function by_equipment($equiID = null, &$filter = null) {
		if ($equiID === null || $equiID === "0")
			throw new Exception('Please supply valid equipment ID');
		if (!is_array($equiID))
			$equiID = array($equiID);
		$requests = array();
		foreach ($equiID as $_p) {
			$requests[] = sprintf('getrelated/equipment/%d/PUBL_has_EQUI', $_p);
		}
		return $this->retrieve($requests, $filter);
	}

	private function retrieve($reqs, &$filter = null) {
		if ($filter !== null && !$filter instanceof CRIS_filter) {
			$filter = new CRIS_filter($filter);
		}
		$data = array();
		foreach ($reqs as $_i) {
			try {
				$data[] = $this->get($_i, $filter);
			} catch (Exception $e) {
				// TODO: logging?
//                $e->getMessage();
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
		$title = preg_quote($tools->numeric_xml_encode($this->attributes["cftitle"]), "/");
		$cristmpl = '<a href="' . $tools->cris_publicweb . 'publication/%d" target="_blank">%s</a>';
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