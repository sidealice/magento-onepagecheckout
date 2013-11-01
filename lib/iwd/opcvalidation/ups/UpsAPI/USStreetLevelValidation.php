<?php
/**
 * Handles the validation of US Shipping Addresses
 * 
 * Copyright (c) 2008, James I. Armes
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the <organization> nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY COPYRIGHT HOLDERS AND CONTRIBUTORS ``AS IS'' AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL COPYRIGHT HOLDERS AND CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * @author James I. Armes <jamesiarmes@gmail.com>
 * @package php_ups_api
 */

/**
 * Handles the validation of US Shipping Addresses
 * 
 * @author James I. Armes <jamesiarmes@gmail.com>
 * @package php_ups_api
 */
class UpsAPI_USStreetLevelValidation extends UpsAPI {
	/**
	 * Node name for the root node
	 * 
	 * @var string
	 */
	const NODE_NAME_ROOT_NODE = '';
	
	/**
	 * Shipping address that we are to validate
	 * 
	 * @access protected
	 * @param array
	 */
	protected $address;
	
	/**
	 * Constructor for the Object
	 * 
	 * @access public
	 * @param array $address array of address parts to validate
	 */
	public function __construct($address) {
		parent::__construct ();
		
		$this->server = $GLOBALS ['ups_street_level_api'] ['server'] . '/ups.app/xml/XAV';

		/// check for zip4
		if(isset($address['zip_code']))
		{
			$pattern = "/([^0-9])/";
			$zip = trim(preg_replace($pattern, '', $address['zip_code']));			
			if(strlen($zip) > 5)
			{
				$zip5	= substr($zip, 0, 5);
				$zip4	= substr($zip, 5);

				if(strlen($zip5)==5 && strlen($zip4)==4)
				{
					$address['zip_code']	= $zip5;
					$address['zip_code4']	= $zip4;
				}
			}
		}
		
		$this->address = $address;
	} // end function __construct()
		

	/**
	 * Builds the XML used to make the request
	 * 
	 * If $customer_context is an array it should be in the format:
	 * $customer_context = array('Element' => 'Value');
	 * 
	 * @access public
	 * @param array|string $cutomer_context customer data
	 * @return string $return_value request XML
	 */
	public function buildRequest($customer_context = null) {
		/** create DOMDocument objects **/
		$address_dom = new DOMDocument ( '1.0' );		

		/** create the AddressValidationRequest element **/
		$address_element = $address_dom->appendChild ( new DOMElement ( 'AddressValidationRequest' ) );
		$address_element->setAttributeNode ( new DOMAttr ( 'xml:lang', 'en-US' ) );
		
		// create the child elements
		$request_element = $this->buildRequest_RequestElement ( $address_element, 'XAV', 3, $customer_context );
		
		$address_element->appendChild ( new DOMElement ( 'MaximumListSize', 3 ) );
		
		$address_element = $address_element->appendChild ( new DOMElement ( 'AddressKeyFormat' ) );

		$create = (! empty ( $this->address ['street'] )) ? $address_element->appendChild ( new DOMElement ( 'AddressLine', $this->address ['street'] ) ) : false;
		$create = (! empty ( $this->address ['city'] )) ? $address_element->appendChild ( new DOMElement ( 'PoliticalDivision2', $this->address ['city'] ) ) : false;
		$create = (! empty ( $this->address ['state'] )) ? $address_element->appendChild ( new DOMElement ( 'PoliticalDivision1', $this->address ['state'] ) ) : false;
		$create = (! empty ( $this->address ['zip_code'] )) ? $address_element->appendChild ( new DOMElement ( 'PostcodePrimaryLow', $this->address ['zip_code'] ) ) : false;
		$create = (! empty ( $this->address ['zip_code4'] )) ? $address_element->appendChild ( new DOMElement ( 'PostcodeExtendedLow', $this->address ['zip_code4'] ) ) : false;
		$create = (! empty ( $this->address ['country'] )) ? $address_element->appendChild ( new DOMElement ( 'CountryCode', $this->address ['country'] ) ) : false;

		unset ( $create );
				
		return parent::buildRequest().$address_dom->saveXML();

	} // end function buildRequest()

	/**
	 * Returns the type of match(s)
	 * 
	 * @access public
	 * @return string $return_value whether or not a full or partial match was
	 * found
	 */
	public function getMatchType() {
		// check if we received any matched
		if (! isset ( $this->response_array ['AddressClassification'] )) {
			return 'Unknown';
		} // end if we received no matches
		

		$match_array = $this->response_array ['AddressClassification'];
		if(isset($match_array ['Code']))
		{
			if($match_array ['Code'] == 0)
				return 'Unknown';
				
			if($match_array ['Code'] == 1)
				return 'Commercial';
			
			if($match_array ['Code'] == 2)
				return 'Residential';
		}
		
		return 'Unknown';
	} // end function getMatchType()
	

	/**
	 * Returns the name of the servies response root node
	 * 
	 * @access protected
	 * @return string
	 * 
	 * @todo remove after phps self scope has been fixed
	 */
	protected function getRootNodeName() {
		return self::NODE_NAME_ROOT_NODE;
	} // end function getRootNodeName()
	

	/**
	 * Checks a match type to see if it is valid
	 * 
	 * @access protected
	 * @param string $match_type match type to validate
	 * @return bool whether or not the match type is valid
	 */
	protected function validateMatchType($match_type) {
		// declare the valid match types
		$valid_match_types = array ('Unknown', 'Commercial', 'Residential');
		
		return in_array ( $match_type, $valid_match_types );
	} // end function validateMatchType()
} // end class UpsAPI_USAddressValidation


?>
