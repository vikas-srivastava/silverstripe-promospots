<?php 

class PromoAdmin extends LeftAndMain {

	static $url_segment = 'promos';
	static $menu_title = 'Promos';

	public function init() {
		parent::init();
		
		/* UI theme. Should this be in JQuery13? */
		Requirements::css('promospots/css/theme_ss/ui.tabs.css');
		$ui = array('core', 'theme', 'resizable', 'datepicker');
		foreach ($ui as $css) Requirements::css("promospots/css/cupertino/ui.$css.css");		
		
		/* Require the UI code I need */
		JQuery13::requireUI('ui.core', 'ui.tabs', 'ui.draggable', 'ui.droppable', 'ui.sortable', 'ui.resizable', 'ui.datepicker', 'effects.core', 'effects.scale');
		
		/* And a single extra library */
		Requirements::javascript('promospots/javascript/jquery.json.js');
		
		/* My (Hamish Friedlander's) code. This should be pulled into a different module or something once I'm done with it */
		Requirements::javascript('promospots/javascript/jquery.stringinterp.js');
		Requirements::javascript('promospots/javascript/concrete.js');
		
		Requirements::css('promospots/css/PromoAdmin.css');
		Requirements::javascript('promospots/javascript/PromoAdmin.js');
		
		Requirements::customScript($this->PromoSections());
		
		Requirements::block(THIRDPARTY_DIR . '/jquery/plugins/livequery/jquery.livequery.js');
	}
	
	public function PageSelectorField() {
		$map = array();
		$pages = HasPromosDecorator::getPromoPages();
		
		foreach ($pages as $page) {
			$spaces = $page->getPromoSpaces();
			if (count($spaces) == 1) $map["{$page->ID}:{$spaces[0]}"] = "{$page->Title}";
			else foreach ($spaces as $space) {
				$map["{$page->ID}:{$space}"] = "{$page->Title} - {$space}";
			}
		}
		
		$optionsetField = new DropdownField('PageID', 'PageID', $map);
		return $optionsetField->Field();
	}	
	
	public function Page($req) {
		list($id, $space) = explode(':', $req->param('ID'));
		$id = is_numeric($id) ? (int)$id : 0;
		
		if ($req->isPOST()) {
			$page = DataObject::get_by_id('SiteTree', $id);
			$foundSections = array();
			
			$data = Convert::json2obj($req->postVar('data'));
			//Debug::dump($data);exit();
			
			foreach ($data as $sectionData) {
				if (isset($sectionData->id)) {
					$section = DataObject::get_by_id('PromoSection', $sectionData->id);
				}
				else {
					$section = singleton($sectionData->templateClass)->NewInstance();	
					$section->PageID = $id;
					$section->Space = $space;
				}
				
				$section->Order = $sectionData->order;
				$section->write();
				$foundSections[] = $section->ID;
				
				$section->PromoSpots()->removeAll();
				
				foreach ($sectionData->spots as $spotName => $spotItems) {
					foreach ($spotItems as $spotItem) {
						$info = explode(':', $spotItem->ID);
						$spot = new PromoSpot(array(
							'Spot' => $spotName,
							'Start' => $spotItem->Start,
							'Length' => $spotItem->Length,
							'ItemClassName' => $info[0],
							'ItemID' => $info[1],
							'PromoSectionID' => $section->ID
						));
						$spot->write();
					}
				}
			}
			
			foreach ($page->getPromoSpace($space) as $section) {
				if (!in_array($section->ID, $foundSections)) {
					$section->PromoSpots()->removeAll();
					$section->delete();
				}
			}
		}
		else {
			$page = DataObject::get_by_id('Page', $id);
			$sections = array();
			foreach ($page->getPromoSpace($space) as $section) $sections[] = $section->Info();
			$res = array(
				'PromoSections' => $sections
			);
			
			return Convert::raw2json($res);
		}
	}
	
	public function PromoSections() {
		$sections = array();
		
		foreach (ClassInfo::subclassesFor('PromoSectionTemplate') as $class) {
			if ($class == 'PromoSectionTemplate' || !singleton($class)->stat('name')) continue;
			$sections[] = singleton($class)->Info();
		}
		
		return 'jQuery(function($){$("#PromoAvailableSections").concrete("ss.promospots").display('.Convert::raw2json(array( 'sections' => $sections )).');})';
	}
	
	public function AvailableItemsFilterFields($req) {
		list($id, $space) = explode(':', $req->param('ID'));
		
		$id = is_numeric($id) ? (int)$id : 0;
		$template = $req->param('OtherID');
		
		$fields = singleton($template)->ItemProvider()->SearchContext()->getFields();
		
		$html = '';
		foreach ($fields as $field) $html .= $field->FieldHolder();
		return $html;
	}
	
	public function AvailableItems($req) {
		list($id, $space) = explode(':', $req->param('ID'));
		$id = is_numeric($id) ? (int)$id : 0;
		
		$template = singleton($req->param('OtherID'));
		$itemProvider = $template->ItemProvider();

		$query = $itemProvider->Query(DataObject::get_by_id('Page', $id), $req->requestVars());
		$query->limit(100);
		
		$list = array();
		foreach ($query->execute() as $record) { $label = $itemProvider->Label($record); $list[] = "<li alt='Page:{$record['ID']}'>{$label}</li>"; }
		return '<ul class="PromoAvailableArticles">'. implode("\n", $list) . '</ul>'; 
	}
}