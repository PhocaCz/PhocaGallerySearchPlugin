<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );




class plgSearchPhocaGallery extends JPlugin
{

	public function __construct(& $subject, $config) {
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	function onContentSearchAreas() {
		static $areas = array(
			'phocagallery' => 'PLG_SEARCH_PHOCAGALLERY_PHOCAGALLERY'
			);
			return $areas;
	}

	function onContentSearch( $text, $phrase = '', $ordering = '', $areas = null ) {


       /* if ($context == 'com_finder.indexer') {
            return true;
        }*/

        // Include Phoca Gallery
        if (!JComponentHelper::isEnabled('com_phocagallery', true)) {
            echo '<div class="alert alert-danger">Phoca Gallery Error: Phoca Gallery component is not installed or not published on your system</div>';
            return;
        }

        if (!class_exists('PhocaGalleryLoader')) {
            require_once( JPATH_ADMINISTRATOR.'/components/com_phocagallery/libraries/loader.php');
        }

        phocagalleryimport('phocagallery.path.path');
        phocagalleryimport('phocagallery.path.route');
        phocagalleryimport('phocagallery.library.library');
        phocagalleryimport('phocagallery.text.text');
        phocagalleryimport('phocagallery.access.access');
        phocagalleryimport('phocagallery.file.file');
        phocagalleryimport('phocagallery.file.filethumbnail');
        phocagalleryimport('phocagallery.image.image');
        phocagalleryimport('phocagallery.image.imagefront');
        phocagalleryimport('phocagallery.render.renderfront');
        phocagalleryimport('phocagallery.render.renderadmin');
        phocagalleryimport('phocagallery.render.renderdetailwindow');
        phocagalleryimport('phocagallery.ordering.ordering');
        phocagalleryimport('phocagallery.picasa.picasa');
        phocagalleryimport('phocagallery.html.category');


	    $db								= JFactory::getDbo();
		$app							= JFactory::getApplication();
		$user							= JFactory::getUser();
		$groups							= implode(',', $user->getAuthorisedViewLevels());
		$component						= 'com_phocagallery';
		$paramsC						= JComponentHelper::getParams($component) ;
		$display_access_category 		= $paramsC->get( 'display_access_category', 1 );


		if (is_array( $areas )) {
			if (!array_intersect( $areas, array_keys( $this->onContentSearchAreas() ) )) {
				return array();
			}
		}

		$limit			= $this->params->get( 'search_limit', 20 );
		$imgLink		= $this->params->get( 'image_link', 0 );
		$display_images	= $this->params->get( 'display_images', 0 );

		$text = trim( $text );
		if ($text == '') {
			return array();
		}

		$section = JText::_( 'PLG_SEARCH_PHOCAGALLERY_PHOCAGALLERY');


		switch ($ordering)
		{
			case 'oldest':
				$order = 'a.date ASC';
				$orderC = 'a.date ASC';
				break;

			case 'popular':
				$order = 'a.hits DESC';
				$orderC = 'a.hits DESC';
				break;

			case 'alpha':
				$order = 'a.title ASC';
				$orderC = 'a.title ASC';
				break;

			case 'category':
				$order = 'c.title ASC, a.title ASC';
				$orderC = 'a.title ASC';
				break;

			case 'newest':
			default:
				$order = 'a.date DESC';
				$orderC = 'a.date DESC';
		}

		$wheres	= array();
		switch ($phrase)
		{
			case 'exact':
				$text		= $db->quote('%'.$db->escape($text, true).'%', false);
				$wheres2	= array();
				$wheres2[]	= 'a.title LIKE '.$text;
				$wheres2[]	= 'a.alias LIKE '.$text;
				//$wheres2[]	= 'a.name LIKE '.$text;
				$wheres2[]	= 'a.metakey LIKE '.$text;
				$wheres2[]	= 'a.metadesc LIKE '.$text;
				$wheres2[]	= 'a.description LIKE '.$text;

				$wheres2[]	= 't.title LIKE '.$text;
				$wheres2[]	= 't.alias LIKE '.$text;

				$where		= '(' . implode(') OR (', $wheres2) . ')';
				break;

			case 'all':
			case 'any':
			default:
				$words	= explode(' ', $text);

				$wheres = array();
				foreach ($words as $word)
				{
					$word		= $db->quote('%'.$db->escape($word, true).'%', false);

					$wheres2	= array();
					$wheres2[]	= 'a.title LIKE '.$word;
					$wheres2[]	= 'a.alias LIKE '.$word;
					//$wheres2[]	= 'a.name LIKE '.$word;
					$wheres2[]	= 'a.metakey LIKE '.$word;
					$wheres2[]	= 'a.metadesc LIKE '.$word;
					$wheres2[]	= 'a.description LIKE '.$word;

					$wheres2[]	= 't.title LIKE '.$word;
					$wheres2[]	= 't.alias LIKE '.$word;

					$wheres[]	= implode(' OR ', $wheres2);
				}
				$where	= '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				break;
		}

		$rows = array();
		// - - - - - -
		// Categories
		// - - - - - -
		$query	= $db->getQuery(true);
		$query->select('a.id, a.title AS title, a.alias, a.date AS created, a.access, a.accessuserid, t.id as tagid, t.title as tagtitle, t.alias as tagalias,'
		. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
		. ' a.description AS text,'
		. ' CONCAT_WS( " / ", '.$db->quote($section).', a.title ) AS section,'
		. ' "2" AS browsernav');
		$query->from('#__phocagallery_categories AS a');
		//$query->innerJoin('#__categories AS c ON c.id = a.catid');
		//$query->where('('.$where.')' . ' AND a.state in ('.implode(',',$state).') AND  a.published = 1 AND a.approved = 1 AND  a.access IN ('.$groups.')');


		$query->leftJoin('#__phocagallery_tags_ref AS tr ON tr.imgid = a.id');
		$query->leftJoin('#__phocagallery_tags AS t ON t.id = tr.tagid');
		$query->where('('.$where.')' . ' AND  a.published = 1 AND a.approved = 1 AND  a.access IN ('.$groups.')');
		$query->group('a.id');
		$query->order($orderC);

		// Filter by language
		if ($app->isClient('site') && $app->getLanguageFilter()) {
			$tag = JFactory::getLanguage()->getTag();
			$query->where('a.language in (' . $db->quote($tag) . ',' . $db->quote('*') . ')');
			//$query->where('c.language in (' . $db->quote($tag) . ',' . $db->quote('*') . ')');
		}

		$db->setQuery( $query, 0, $limit );
		$listCategories = $db->loadObjectList();
		$limit -= count($listCategories);



		if(isset($listCategories)) {
			foreach($listCategories as $key => $value) {

				// USER RIGHT - ACCESS - - - - - - - - - - -
				$rightDisplay = 1;//default is set to 1 (all users can see the category)

				if (!empty($value)) {
					$rightDisplay = PhocaGalleryAccess::getUserRight('accessuserid', $value->accessuserid, $value->access, $user->getAuthorisedViewLevels(), $user->get('id', 0), $display_access_category);
				}
				if ($rightDisplay == 0) {
					unset($listCategories[$key]);
				} else {
					$listCategories[$key]->href = $link = JRoute::_(PhocaGalleryRoute::getCategoryRoute($value->id, $value->alias));
				}
				// - - - - - - - - - - - - - - - - - - - - -
			}
		}
		$rows[] = $listCategories;

		// Images
		if ( $limit > 0 ) {

			// - - - - - -
			// Images
			// - - - - - -
			$query	= $db->getQuery(true);
			$query->select(' CASE WHEN CHAR_LENGTH(a.title) THEN CONCAT_WS(\': \', c.title, a.title)
		ELSE c.title END AS title, '
			. ' CASE WHEN CHAR_LENGTH(a.description) THEN CONCAT_WS(\': \', a.title,
		a.description) ELSE a.title END AS text, '
			. ' a.id, a.filename, a.extm, a.exts, a.alias, a.date AS created, c.accessuserid as accessuserid, c.access as cataccess,'
			. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug, '
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END AS catslug, '
			. ' CONCAT_WS( " / ", '.$db->quote($section).', a.title ) AS section,'
			. ' "2" AS browsernav, c.id as catid, c.alias as catalias, c.title as ctitle');
			$query->from('#__phocagallery AS a');
			$query->innerJoin('#__phocagallery_categories AS c ON c.id = a.catid');
			//$query->where('('.$where.')' . ' AND a.state in ('.implode(',',$state).') AND  a.published = 1 AND a.approved = 1 AND c.published = 1 AND c.approved = 1 AND  c.access IN ('.$groups.')');

			$query->leftJoin('#__phocagallery_tags_ref AS tr ON tr.imgid = a.id');
			$query->leftJoin('#__phocagallery_tags AS t ON t.id = tr.tagid');

			$query->where('('.$where.')' . ' AND  a.published = 1 AND a.approved = 1 AND c.published = 1 AND c.approved = 1 AND  c.access IN ('.$groups.')');
			//$query->group('a.id');
			$query->order($order);

			// Filter by language
			if ($app->isClient('site') && $app->getLanguageFilter()) {
				$tag = JFactory::getLanguage()->getTag();
				$query->where('a.language in (' . $db->quote($tag) . ',' . $db->quote('*') . ')');
				$query->where('c.language in (' . $db->quote($tag) . ',' . $db->quote('*') . ')');
			}

			$db->setQuery( $query, 0, $limit );
			$listImages = $db->loadObjectList();

			if(isset($listImages)) {
				foreach($listImages as $key => $value) {
					// USER RIGHT - ACCESS - - - - - - - - - - -
					$rightDisplay = 1;//default is set to 1 (all users can see the category)

					if (!empty($value)) {
						$rightDisplay = PhocaGalleryAccess::getUserRight('accessuserid', $value->accessuserid, $value->cataccess, $user->getAuthorisedViewLevels(), $user->get('id', 0), $display_access_category);
					}
					if ($rightDisplay == 0) {
						unset($listImages[$key]);
					} else {

						if ($display_images == 1) {
							if (isset($value->extm) && $value->extm != '') {
								$listImages[$key]->image=$value->extm;
							} else if (isset($value->filename) && $value->filename != '') {
								$filename 				= str_replace('//', '/', $value->filename);
								$filename				= str_replace(DS, '/', $filename);
								$folderArray			= explode('/', $filename);
								$countFolderArray		= count($folderArray);
								$lastArrayValue 		= $countFolderArray - 1;
								//$fileNameThumb 			= 'phoca_thumb_m_'. $folderArray[$lastArrayValue];
								$fileNameThumb			= str_replace($folderArray[$lastArrayValue], '/thumbs/phoca_thumb_m_'. $folderArray[$lastArrayValue], $value->filename);
								$listImages[$key]->image= JURI::base(true). '/images/phocagallery/' .$fileNameThumb;
							}
						} else if ($display_images == 2) {
							if (isset($value->exts) && $value->exts != '') {
								$listImages[$key]->image=$value->exts;
							} else if (isset($value->filename) && $value->filename != '') {
								$filename 				= str_replace('//', '/', $value->filename);
								$filename				= str_replace(DS, '/', $filename);
								$folderArray			= explode('/', $filename);
								$countFolderArray		= count($folderArray);
								$lastArrayValue 		= $countFolderArray - 1;
								//$fileNameThumb 			= 'phoca_thumb_s_'. $folderArray[$lastArrayValue];
								$fileNameThumb2			= str_replace($folderArray[$lastArrayValue], '/thumbs/phoca_thumb_s_'. $folderArray[$lastArrayValue], $value->filename);
								$listImages[$key]->image= JURI::base(true). '/images/phocagallery/' .$fileNameThumb2;
							}
						}

						if ($imgLink == 0) {
							$listImages[$key]->href = JRoute::_(PhocaGalleryRoute::getCategoryRoute($value->catid, $value->catalias));
						} else {
							$listImages[$key]->href = JRoute::_(PhocaGalleryRoute::getImageRoute($value->id, $value->catid, $value->alias, $value->catalias));
						}

					}
					// - - - - - - - - - - - - - - - - - - - - -
				}
			}
			$rows[] = $listImages;
		}

		$results = array();
		if(count($rows)) {
			foreach($rows as $row) {
				$results = array_merge($results, (array) $row);
			}
		}

		return $results;
	}
}
