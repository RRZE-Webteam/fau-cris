<?php

namespace FAU\CRIS;

defined('ABSPATH') || exit;


function getPageLanguage($postID) {
	$page_lang_meta = get_post_meta($postID, 'fauval_langcode', true);
	if ($page_lang_meta != '') {
		$page_lang = ($page_lang_meta == 'de') ? 'de' : 'en';
	} else {
		$page_lang = strpos(get_locale(), 'de') === 0 ? 'de' : 'en';
	}
	return $page_lang;
}

/*
* Array zur Definition des Filters für Publikationen
*/
function publication_filter($parameter) {
	var_dump($parameter);
	$filter = array();
	if ($parameter['year'] !== '')
		$filter['publyear__eq'] = $parameter['year'];
	if ($parameter['start'] !== '')
		$filter['publyear__ge'] = $parameter['start'];
	if ($parameter['end'] !== '')
		$filter['publyear__le'] = $parameter['end'];
	if ($parameter['type'] !== '') {
		if (strpos($parameter['type'], ',')) {
			$type = str_replace(' ', '', $parameter['type']);
			$types = explode(',', $type);
			foreach($types as $v) {
				$pubTyp[] = getType('publications', $v);
			}
		} else {
			$pubTyp = (array) getType('publications', $type);
		}
		if (empty($pubTyp)) {
			$output = '<p>' . __('Falscher Parameter für Publikationstyp', 'fau-cris') . '</p>';
			return $output;
		}
		$filter['publication type__eq'] = $pubTyp;
	}
	if ($parameter['subtype'] !== '') {
		$subtype = str_replace(' ', '', $parameter['subtype']);
		$subtypes = explode(',', $subtype);
		foreach($subtypes as $v) {
			$pubSubTyp[] = getType('publications', $v, $pubTyp[0]);
		}
		if (empty($pubSubTyp)) {
			$output = '<p>' . __('Falscher Parameter für Publikationssubtyp', 'fau-cris') . '</p>';
			return $output;
		}
		$filter['subtype__eq'] = $pubSubTyp;
	}
	if ($parameter['fau'] !== '') {
		if ($parameter['fau'] == 1) {
			$filter['fau publikation__eq'] = 'yes';
		} elseif ($parameter['fau'] == 0) {
			$filter['fau publikation__eq'] = 'no';
		}
	}
	if ($parameter['peerreviewed'] !== '') {
		if ($parameter['peerreviewed'] == 1) {
			$filter['peerreviewed__eq'] = 'Yes';
		} elseif ($parameter['peerreviewed'] == 0) {
			$filter['peerreviewed__eq'] = 'No';
		}
	}
	if ($parameter['language'] !== '') {
		$language = str_replace(' ', '', $parameter['language']);
		$pubLanguages = explode(',', $language);
		$filter['language__eq'] = $pubLanguages;
	}
	if ($parameter['curation'] == 1) {
		$filter['relation curationsetting__eq'] = 'curation_accepted';
	}
	if (count($filter))
		return $filter;
	return null;
}

function getType($object, $short, $type = '') {
	if ($type == '') {
		foreach (CRIS_Dicts::$typeinfos[$object] as $k => $v) {
			if($v['short'] == $short)
				return $k;
			if (array_key_exists('short_alt', $v) && $v['short_alt'] == $short) {
				return $k;
			}
		}
	} else {
		foreach (CRIS_Dicts::$typeinfos[$object][$type]['subtypes'] as $k => $v) {
			if($v['short'] == $short)
				return $k;
		}
	}
}