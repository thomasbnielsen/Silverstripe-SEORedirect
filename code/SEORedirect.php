<?php
/**
 * SEORedirect module
 *
 * @author Nobrainer Web <mail@nobrainer.dk>
 * @version 1.0
 */
class SEORedirect extends DataObjectDecorator{
	/**
	 * Method to construct
	 */
	public function __construct(){
		parent::__construct();
	}
	
	public function extraStatics($class = null, $extension = null) {
		return array(
            'has_many' => array(
                'SEORedirectUrls' => 'SEORedirectUrl'
            )
        );
	}
	
	/**
	 * Update CMS fields
	 */
	public function updateCMSFields(FieldList $fields){
		//Redirect tabs
		$Redirect = new ComplexTableField(
			$this,
	    	'SEORedirectUrls',
	    	'SEORedirectUrl',
	    	array(
				'Source' 		    => 'Source',
				'TargetLink' 		=> 'Target link',
                'TargetPage.Title'  => 'Target page',
                'IsActive'  		=> 'Active',
	    	),
	    	'getCMSFields_forPopup',
			"\"TargetPageID\" = '".$this->owner->ID."' "
		);
		
		$Redirect->setAddTitle('an url');
		$Redirect->popupClass = 'SEORedirect_Popup';
        
        $fields->addFieldToTab('Root.Content.Redirect', $Redirect);
	}
	
	/**
	 * Method to handle request
	 */
	public static function handleRequest($request){
		//get request variables
		$baseURL    = str_replace('/index.php/', '/', Director::BaseURL());
		$requestURI = str_replace('/index.php/', '/', $_SERVER['REQUEST_URI']);
		$link       = rtrim(str_replace($baseURL, '', $requestURI), '/');
		
		if( $target = self::get_by_link($link)  ){
			$TargetLink = $target->TargetLink ? $target->TargetLink :
				( $target->TargetPage()->Exists() ? str_replace('/index.php/', '',$target->TargetPage()->Link()) : null );
			
            if($TargetLink){
				//Forward parameters to target
				if( isset($target->ForwardParameters) && $target->ForwardParameters ){
					$url_data = parse_url($link);
					
					if( isset($url_data["query"]) ){
						if (strpos($TargetLink, '?') === false) {
							$TargetLink .= '?';
						} else if (substr($TargetLink, strlen($TargetLink)-1, 1) != '?' && strpos($TargetLink, '&') === false) {
							$TargetLink .= '&';			
						}
						
						$TargetLink .= $url_data["query"];
					}
				}
				
				$response = new SS_HTTPResponse();
				$response->redirect($TargetLink, $target->Code);
				
				return $response;
			}
		}
		
		return false;
	}
	
	/**
	 * Returns a link mapping for a link if one exists.
	 *
	 * @param  string $link
	 * @return LinkMapping
	 */
	public static function get_by_link($link) {
		return DataObject::get_one('SEORedirectUrl', " MD5(`Source`) = '".md5( strtolower( trim($link) ) )."' AND `Active` = 1 ");
	}
}

/**
 * SEORedirectUrl class
 */
class SEORedirectUrl extends DataObject{
	/**
	 * Human-readable singular name.
	 * @var string
	 */
	public static $singular_name = 'url';
	
	/**
	 * Human-readable pluaral name
	 * @var string
	 */
	public static $plural_name = 'urls';
	
	/**
	 * Database fields
	 */
	public static $db = array(
		'Source'     		=> 'Varchar(300)',
		'TargetLink' 		=> 'Varchar(300)',
		'Code'       		=> 'Enum("301, 302", "301")',
		'Active'     	    => 'Boolean(1)',
		'ForwardParameters' => 'Boolean(0)'
	);

	public static $has_one = array(
		'TargetPage' => 'SiteTree'
	);

	public static $summary_fields = array(
		'Source',
		'TargetLink',
		'TargetPage.Title',
		'IsActive'
	);
	
	public function getIsActive(){
		return $this->Active ? 'Yes' : 'No';
	}

	/**
	 * Returns a link mapping for a link if one exists.
	 *
	 * @param  string $link
	 * @return SEORedirectUrl
	 */
	public static function get_by_link($link) {
		return DataObject::get_one('SEORedirectUrl', sprintf(
			'"Source" = \'%s\'', Convert::raw2sql(self::unify_link($link))
		));
	}

	/**
	 * Unifies a link so mappings are predictable.
	 *
	 * @param  string $link
	 * @return string
	 */
	public static function unify_link($link) {
		return strtolower(trim(Director::makeRelative(strtok($link, '?')), '/'));
	}
	
	/*
	* Method to show CMS fields for Popup for creating or updating
	*/
	public function getCMSFields_forPopup()
	{
		//init variables
		$fields = new FieldSet();
		
		$fields->push( new CheckboxField('Active', 'Active') );
		$fields->push( new CheckboxField('ForwardParameters', 'Forward parameters') );
		
		$fields->push( new TextField('Source', 'Source') );
		
		$target = new TextField('TargetLink', 'Target link');
		$target->setRightTitle('External url which redirects to, leave blank to redirect to current page');
		$fields->push($target);
		
        $fields->push( new DropdownField('Code', 'Redirect code', $this->dbObject('Code')->enumValues()) );
		
		return $fields;
	}
	
	/**
	 * On before save record
	 */
	public function onBeforeWrite(){
		$this->Source = strtolower( ltrim( rtrim( trim($this->Source), '/'), '/' ) );
        
        if( $this->TargetLink ){
			$this->TargetLink = strtolower( trim($this->TargetLink) );
		}
        
        parent::onBeforeWrite();
	}
}

/**
 * SEORedirect_Popup
 */
class SEORedirect_Popup extends ComplexTableField_Popup{
	/**
	 * Construct
	 */
	public function __construct($controller, $name, $fields, $validator, $readonly, $dataObject){
		//extends validator
		$validator = new SEORedirectUrl_Validator( array('Source') );
		
		parent::__construct($controller, $name, $fields, $validator, $readonly, $dataObject);
	} 
}

/**
 * SEORedirectUrl validation class
 */
class SEORedirectUrl_Validator extends RequiredFields{
	/**
	 * server-side validator
	 */
	public function php($data){
		$form    = $this->form;
		$valid   = parent::php($data);
		$regex   = '/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i';
		
        $source  = strtolower( ltrim(rtrim(trim($data['Source']),'/'),'/') );
        
		/**
		 * Ensure that the source is unique
		 */
		if($valid){
			$query = new SQLQuery();
			$where = null;
			
			if( isset($form->record->ID) && $form->record->ID > 0 ){
				$where = " AND `ID` != ".(int)$form->record->ID." ";
			}
			
			$res = $query->select(' COUNT(`ID`) ')->from('SEORedirectUrl')
					->where(" MD5(`Source`) = '".md5($source)."' $where ")
					->execute()->value();
			
			if($res){
				$this->validationError(
					"Source", "Source pattern is already exist", "required"
				);
				
				$valid = false;
			}
		}
        
        /**
         * Ensure that the source does not match with any URLSegments
         */
        if($valid){
            $query = new SQLQuery();
            $res   = false;
            
            $res = self::URLSegmentExists($query, 0, explode('/', $source) );
            
            //$res = DataObject::get_one('SiteTree', " LOWER(`URLSegment`) = '".$source."' ");
            
			if($res){
				$this->validationError(
					"Source", "Source pattern is matched with a page URLSegment", "required"
				);
				
				$valid = false;
			}
		}
        
		
		/**
		 * Validate external url if it is given
		 */
		if( $valid && isset($data['TargetLink']) && $data['TargetLink'] != '' && ! (bool)preg_match($regex, $data['TargetLink']) ){
			$this->validationError(
				"TargetLink", "Please fill in a valid url", "required"
			);
			
			$valid = false;
		}
		
		return $valid;
	}
    
    
    /**
     * check if URLSegment is already exist or not
     */
    protected function URLSegmentExists($SQLQuery, $ParentID = 0, $URLSegments = array()){
        if( count($URLSegments) > 1 ){
            $URLSegments = array_values($URLSegments);
            
            if( isset($URLSegments[0]) ){
                $res = DataObject::get_one('SiteTree', " LOWER(`URLSegment`) = '".$URLSegments[0]."' AND ParentID = ".(int)$ParentID);
                
                unset($URLSegments[0]);
                
                if($res){
                    return self::URLSegmentExists($SQLQuery, $res->ParentID, $URLSegments);
                }
            }
        }
        
        if( count($URLSegments) == 1 ){
            $URLSegments = array_values($URLSegments);
            
            return DataObject::get_one('SiteTree', " LOWER(`URLSegment`) = '".$URLSegments[0]."' ");
        }
        
        return false;
    }

}