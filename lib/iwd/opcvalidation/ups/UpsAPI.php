<?php

define ( 'BASE_PATH', dirname ( dirname ( __FILE__ ) ) );
error_reporting ( E_ALL );

$pear	= Mage::getBaseDir('lib').'/PEAR';

// set the pear include path
$separator = ':';
if (strtoupper ( substr ( PHP_OS, 0, 3 ) ) === 'WIN') {
	$separator = ';';
} // end if this is a windows server

$include_path = ini_get ( 'include_path' ) . $separator . $pear;

ini_set ( 'include_path', $include_path );

abstract class UpsAPI {
	/**
	 * Status code for a failed request
	 * 
	 * @var integer
	 */
	const RESPONSE_STATUS_CODE_FAIL = 0;
	
	/**
	 * Status code for a successful request
	 * 
	 * @var integer
	 */
	const RESPONSE_STATUS_CODE_PASS = 1;
	
	/**
	 * Access key provided by UPS
	 * 
	 * @access protected
	 * @var string
	 */
	protected $access_key;
	
	/**
	 * Developer key provided by UPS
	 * 
	 * @access protected
	 * @var string
	 */
	protected $developer_key;
	
	/**
	 * Password used to access UPS Systems
	 * 
	 * @access protected
	 * @var string
	 */
	protected $password;
	
	/**
	 * Response from the server as XML
	 * 
	 * @access protected
	 * @var DOMDocument
	 */
	protected $response;
	
	/**
	 * Response from the server as an array
	 * 
	 * @access protected
	 * @var array
	 */
	protected $response_array;
	
	/**
	 * Root Node for the repsonse XML
	 * 
	 * @access protected
	 * @var DOMNode
	 */
	protected $root_node;
	
	/**
	 * UPS Server to send Request to
	 * 
	 * @access protected
	 * @var string
	 */
	protected $server;
	
	/**
	 * Username used to access UPS Systems
	 * 
	 * @access protected
	 * @var string
	 */
	protected $username;
	
	/**
	 * xpath object for the response XML
	 * 
	 * @access protected
	 * @var DOMXPath
	 */
	protected $xpath;
	
	/**
	 * Sets up the API Object
	 * 
	 * @access public
	 */
	public function __construct() {
		/** Set the Keys on the Object **/
		$this->access_key = $GLOBALS['ups_api']['access_key'];
		$this->developer_key = $GLOBALS['ups_api']['developer_key'];
		
		
		/** Set the username and password on the Object **/
		$this->password = $GLOBALS['ups_api']['password'];
		$this->username = $GLOBALS['ups_api']['username'];
	} // end funciton __construct()
	
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
		// create the access request element
		$access_dom = new DOMDocument('1.0');
		$access_element = $access_dom->appendChild(
			new DOMElement('AccessRequest'));
		$access_element->setAttributeNode(new DOMAttr('xml:lang', 'en-US'));
		
		// create the child elements
		$access_element->appendChild(
			new DOMElement('AccessLicenseNumber', $this->access_key));
		$access_element->appendChild(
			new DOMElement('UserId', $this->username));
		$access_element->appendChild(
			new DOMElement('Password', $this->password));
		
		return $access_dom->saveXML();
	} // end function buildRequest()
	
	/**
	 * Returns the error message(s) from the response
	 * 
	 * @return array
	 */
	public function getError() {
		// iterate over the error messages
		$errors = $this->xpath->query('Response/Error', $this->root_node);
		$return_value = array();
		foreach ($errors as $error) {
			$return_value[] = array(
				'severity' => $this->xpath->query('ErrorSeverity', $error)
					->item(0)->nodeValue,
				'code' => $this->xpath->query('ErrorCode', $error)
					->item(0)->nodeValue,
				'description' => $this->xpath->query('ErrorDescription', $error)
					->item(0)->nodeValue,
				'location' => $this->xpath
					->query('ErrorLocation/ErrorLocationElementName', $error)
					->item(0)->nodeValue,
			); // end $return_value
		} // end for each error message
		
		return $return_value;
	} // end function getError()
	
	/**
	 * Checks to see if a repsonse is an error
	 * 
	 * @access public
	 * @return boolean 
	 */
	public function isError() {
		// check to see if the request failed
		$status = $this->xpath->query('Response/ResponseStatusCode',
			$this->root_node);
		if ($status->item(0)->nodeValue == self::RESPONSE_STATUS_CODE_FAIL) {
			return true;
		} // end if the request failed
		
		return false;
	} // end function isError
	
	/**
	 * Send a request to the UPS Server using xmlrpc
	 * 
	 * @access public
	 * @params string $request_xml XML request from the child objects
	 * buildRequest() method
	 * @params boool $return_raw_xml whether or not to return the raw XML from
	 * the request
	 * 
	 * @todo remove array creation after switching over to xpath
	 */
	public function sendRequest($request_xml, $return_raw_xml = false) {
		include_once('XML/Unserializer.php');
		
		// create the context stream and make the request
		$context = stream_context_create(array(
			'http' => array(
				'method' => 'POST',
				'header' => 'Content-Type: text/xml',
				'content' => $request_xml,
			),
		));
		$response = file_get_contents($this->server, false, $context);

		// TODO: remove array creation after switching over to xpath
		// create an array from the raw XML data
		$unserializer = new XML_Unserializer(array('returnResult' => true));
		$this->response_array = $unserializer->unserialize($response);
		
		// build the dom objects
		$this->response = new DOMDocument();
		$this->response->loadXML($response);
		$this->xpath = new DOMXPath($this->response);
		$this->root_node = $this->xpath->query(
			'/'.$this->getRootNodeName())->item(0);
		
		// check if we should return the raw XML data
		if ($return_raw_xml) {
			return $response;
		} // end if we should return the raw XML
		
		// return the response as an array
		return $this->response_array;
	} // end function sendRequest()
	
	/**
	 * Builds the Request element
	 * 
	 * @access protected
	 * @param DOMElement $dom_element
	 * @param string $action
	 * @param string $option
	 * @param string|array $customer_context
	 * @return DOMElement
	 */
	protected function buildRequest_RequestElement(&$dom_element, $action,
		$option = null, $customer_context = null) {
		// create the child element
		$request = $dom_element->appendChild(
			new DOMElement('Request'));
		
		// create the children of the Request element
		$transaction_element = $request->appendChild(
			new DOMElement('TransactionReference'));
		$request->appendChild(
			new DOMElement('RequestAction', $action));
		
		// check to see if an option was passed in
		if (!empty($option)) {
			$request->appendChild(
				new DOMElement('RequestOption', $option));
		} // end if an option was passed in
		
		// create the children of the TransactionReference element
		$transaction_element->appendChild(
			new DOMElement('XpciVersion', '1.0'));
		
		// check if we have customer data to include
		if (!empty($customer_context)) {
			// check to see if the customer context is an array
			if (is_array($customer_context)) {
				$customer_element = $transaction_element->appendChild(
					new DOMElement('CustomerContext'));

				// iterate over the array of customer data
				foreach ($customer_context as $element => $value) {
					$customer_element->appendChild(
						new DOMElement($element, $value));
				} // end for each customer data
			} // end if the customer data is an array
			else {
				$transaction_element->appendChild(
					new DOMElement('CustomerContext', $customer_context));
			} // end if the customer data is a string
		} // end if we have customer data to include
		
		return $request;
	} // end function buildRequest_RequestElement()
	
	/**
	 * Returns the name of the servies response root node
	 * 
	 * @access protected
	 * @return string
	 * 
	 * @todo remove after phps self scope has been fixed
	 */
	protected abstract function getRootNodeName();
} // end class UpsAPI
