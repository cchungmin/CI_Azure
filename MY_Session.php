<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); 

require_once 'vendor\autoload.php';

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\Table\Models\Entity;
use WindowsAzure\Table\Models\EdmType;

class MY_Session extends CI_Session
{
	var $sess_use_azure = FALSE;
	var $sess_azure_defaultprotocol = '';
	var $sess_azure_accountname = '';
	var $sess_azure_accountkey = '';
	var $connection_string = '';
	var $tableRestProxy;
    
	function __construct()
    { 

		
		//echo $this->userdata('session_id');
		$this->CI =& get_instance();
			
		$this->sess_use_azure = $this->CI->config->item('sess_use_azure');
		//echo $this->sess_build_conn_string();
		$this->connection_string = $this->sess_build_conn_string();
		// Create table REST proxy.
		$this->tableRestProxy = ServicesBuilder::getInstance()->createTableService($this->connection_string);
		/*if ( ! $this->sess_tableReading() ){
			$this->sess_tableCreating();
		}*/
        parent::__construct();		
    }
	
	function sess_build_conn_string()
	{
		$this->sess_azure_defaultprotocol = $this->CI->config->item('sess_azure_defaultprotocol');
		$this->sess_azure_accountname = $this->CI->config->item('sess_azure_accountname');
		$this->sess_azure_accountkey = $this->CI->config->item('sess_azure_accountkey');
	
		return "DefaultEndpointsProtocol=" . $this->sess_azure_defaultprotocol . ";AccountName=" . $this->sess_azure_accountname . ";AccountKey=" . $this->sess_azure_accountkey;
	}
	
	function sess_tableCreating()
	{		
		if ($this->sess_use_azure === TRUE)
		{
			$entity = new Entity();
			$entity->setPartitionKey("doublereg");
			$entity->setRowKey($this->userdata('session_id'));
			//var_dump($this->userdata);
			$entity->addProperty("session_id", EdmType::STRING, $this->userdata('session_id'));
			$entity->addProperty("ip_address", EdmType::STRING, $this->userdata('ip_address'));
			$entity->addProperty("user_agent", EdmType::STRING, $this->userdata('user_agent'));
			$entity->addProperty("last_activity", EdmType::INT32, $this->userdata('last_activity'));
			$entity->addProperty("user_data", EdmType::STRING, '');	
			try {
			// Create table.
				echo "test3";
				$this->tableRestProxy->insertEntity("sessiontable", $entity);
				//$this->tableRestProxy->createTable("sessiontable");
				$this->sess_tableAdding();
				echo "test4";
				var_dump($this->userdata);
			}
			catch(ServiceException $e){
				$code = $e->getCode();
				$error_message = $e->getMessage();
				echo "create error";
				echo $code.": ".$error_message."<br />";				
				// Handle exception based on error codes and messages.
				// Error codes and messages can be found here: 
				// http://msdn.microsoft.com/en-us/library/windowsazure/dd179438.aspx
			}			
		}
		echo "create";
	}
	
	function sess_tableReading()
	{
		if ($this->sess_use_azure === TRUE)
		{

			$filter = "RowKey eq '" . $this->userdata('session_id') . "'";
			
			try {
				//echo $this->connection_string;
				$result = $this->tableRestProxy->queryEntities("sessiontable", $filter);
			}
			catch(ServiceException $e){
				// Handle exception based on error codes and messages.
				// Error codes and messages are here: 
				// http://msdn.microsoft.com/en-us/library/windowsazure/dd179438.aspx
				$code = $e->getCode();
				$error_message = $e->getMessage();
				echo "readerror";
				echo $code.": ".$error_message."<br />";
				if(!isset($result)) return FALSE;
			}

			$entities = $result->getEntities();

			/*if (count($entities)== 0)
			{
				//$this->sess_tableDeleting();
				//$this->sess_destroy();
				echo "0?" . $this->userdata('session_id');
				return FALSE;
			}	*/		
			

			
			foreach($entities as $entity){
					$user_data = $this->_unserialize($entity->getPropertyValue('user_data'));
					$this->userdata['session_id'] = $entity->getPropertyValue('session_id');
					$this->userdata['ip_address'] = $entity->getPropertyValue('ip_address');
					$this->userdata['user_agent'] = $entity->getPropertyValue('user_agent');
					$this->userdata['last_activity'] = $entity->getPropertyValue('last_activity');
					$this->userdata['user_data'] = $user_data;

				echo "property==";
			}
			
			if (is_array($user_data))
			{
				foreach ($user_data as $key => $val)
				{
					$this->userdata[$key] = $val;
				}
			}
			var_dump($this->userdata);
		}
		
		echo "read";		
		return TRUE;
	}
	
	function sess_tableAdding()
	{
	
		echo "test456";
		
		$custom_userdata = $this->userdata;
		$cookie_userdata = array();

		// Before continuing, we need to determine if there is any custom data to deal with.
		// Let's determine this by removing the default indexes to see if there's anything left in the array
		// and set the session data while we're at it
		foreach (array('session_id','ip_address','user_agent','last_activity') as $val)
		{
			unset($custom_userdata[$val]);
			$cookie_userdata[$val] = $this->userdata[$val];
		}

		// Did we find any custom data?  If not, we turn the empty array into a string
		// since there's no reason to serialize and store an empty array in the DB
		if (count($custom_userdata) === 0)
		{
			$custom_userdata = '';
		}
		else
		{
			// Serialize the custom data array so we can store it
			$custom_userdata = $this->_serialize($custom_userdata);
		}

		if (count($this->userdata) > 0)
		{
			$result = $this->tableRestProxy->getEntity("sessiontable", "doublereg", $this->userdata['session_id']);
			$entity = $result->getEntity();
			echo "ok2";		
			$entity->setPropertyValue('last_activity', $this->now);
			$entity->setPropertyValue('user_data', $custom_userdata);
		}
		echo "test3";
		$this->_set_cookie($cookie_userdata);

		try{
			$this->tableRestProxy->updateEntity("sessiontable", $entity);
		}
		catch(ServiceException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here: 
			// http://msdn.microsoft.com/en-us/library/windowsazure/dd179438.aspx
			$code = $e->getCode();
			$error_message = $e->getMessage();
			echo $code.": ".$error_message."<br />";
		}	
	}
	
	function sess_tableDeleting()
	{
		try {
			// Delete entity.
			$this->tableRestProxy->deleteEntity("sessiontable", "doublereg", $this->userdata('session_id'));
		}
		catch(ServiceException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here: 
			// http://msdn.microsoft.com/en-us/library/windowsazure/dd179438.aspx
			$code = $e->getCode();
			$error_message = $e->getMessage();
			echo $code.": ".$error_message."<br />";
		}
		echo "delete";
	}
	
	function sess_update()
	{
		// We only update the session every five minutes by default
		if (($this->userdata['last_activity'] + $this->sess_time_to_update) >= $this->now)
		{
			return;
		}

		// Save the old session id so we know which record to
		// update in the database if we need it
		$old_sessid = $this->userdata['session_id'];
		$new_sessid = '';
		while (strlen($new_sessid) < 32)
		{
			$new_sessid .= mt_rand(0, mt_getrandmax());
		}

		// To make the session ID even more secure we'll combine it with the user's IP
		$new_sessid .= $this->CI->input->ip_address();

		// Turn it into a hash
		$new_sessid = md5(uniqid($new_sessid, TRUE));

		// Update the session data in the session data array
		$this->userdata['session_id'] = $new_sessid;
		$this->userdata['last_activity'] = $this->now;

		// _set_cookie() will handle this for us if we aren't using database sessions
		// by pushing all userdata to the cookie.
		$cookie_data = NULL;

		// Update the session ID and last_activity field in the DB if needed
		if ($this->sess_use_database === TRUE)
		{
			// set cookie explicitly to only have our session data
			$cookie_data = array();
			foreach (array('session_id','ip_address','user_agent','last_activity') as $val)
			{
				$cookie_data[$val] = $this->userdata[$val];
			}

			$this->CI->db->query($this->CI->db->update_string($this->sess_table_name, array('last_activity' => $this->now, 'session_id' => $new_sessid), array('session_id' => $old_sessid)));
		}
			
		if ($this->sess_use_azure === TRUE)
		{	
			try{
				$result = $this->tableRestProxy->getEntity("sessiontable", "doublereg", $old_sessid);
				echo "ok";
				$entity = $result->getEntity();			
				$entity->setPropertyValue('last_activity', $this->now);
				$entity->setPropertyValue('session_id', $new_sessid);		
				$this->tableRestProxy->updateEntity("sessiontable", $entity);				
			}
			catch(ServiceException $e){
				// Handle exception based on error codes and messages.
				// Error codes and messages are here: 
				// http://msdn.microsoft.com/en-us/library/windowsazure/dd179438.aspx
				$code = $e->getCode();
				$error_message = $e->getMessage();
				echo $code.": ".$error_message."<br />";
			}
			echo "update";
		}
	}

	function sess_read()
	{
		// Fetch the cookie
		$session = $this->CI->input->cookie($this->sess_cookie_name);
		echo "<br><br>";
		var_dump($session);
		$size = strlen(serialize($session));
		print($size * 8 / 1000);
		echo "<br><br>";
		// No cookie?  Goodbye cruel world!...
		if ($session === FALSE)
		{
			log_message('debug', 'A session cookie was not found.');
			return FALSE;
		}

		// Decrypt the cookie data
		if ($this->sess_encrypt_cookie == TRUE)
		{
			$session = $this->CI->encrypt->decode($session);
		}
		else
		{
			echo " encryption was not used  <br>";
			// encryption was not used, so we need to check the md5 hash
			$hash	 = substr($session, strlen($session)-32); // get last 32 chars
			$session = substr($session, 0, strlen($session)-32);


			// Does the md5 hash match?  This is to prevent manipulation of session data in userspace
			if ($hash !==  md5($session.$this->encryption_key))
			{
				echo $hash . " not <br>";
				echo md5($session.$this->encryption_key). " not <br>";
				//var_dump($session);
				echo " not <br>";	
				log_message('error', 'The session cookie data did not match what was expected. This could be a possible hacking attempt.');
				$this->sess_destroy();
				return FALSE;
			}
			
			if ($hash ===  md5($session.$this->encryption_key))
			{
				echo md5($session.$this->encryption_key). " match <br>";
				echo $hash. " match <br>";	
				//var_dump($session); 
				echo " match <br>";	
			}
		}

	//	echo (substr($session, strlen($session)-32) !==  md5($session.$this->encryption_key));
		
		// Unserialize the session array
		$session = $this->_unserialize($session);

		// Is the session data we unserialized an array with the correct format?
		if ( ! is_array($session) OR ! isset($session['session_id']) OR ! isset($session['ip_address']) OR ! isset($session['user_agent']) OR ! isset($session['last_activity']))
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Is the session current?
		if (($session['last_activity'] + $this->sess_expiration) < $this->now)
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Does the IP Match?
		if ($this->sess_match_ip == TRUE AND $session['ip_address'] != $this->CI->input->ip_address())
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Does the User Agent Match?
		if ($this->sess_match_useragent == TRUE AND trim($session['user_agent']) != trim(substr($this->CI->input->user_agent(), 0, 120)))
		{
			$this->sess_destroy();
			return FALSE;
		}

		// Is there a corresponding session in the DB?
		if ($this->sess_use_database === TRUE)
		{
			$this->CI->db->where('session_id', $session['session_id']);

			if ($this->sess_match_ip == TRUE)
			{
				$this->CI->db->where('ip_address', $session['ip_address']);
			}

			if ($this->sess_match_useragent == TRUE)
			{
				$this->CI->db->where('user_agent', $session['user_agent']);
			}

			$query = $this->CI->db->get($this->sess_table_name);

			// No result?  Kill it!
			if ($query->num_rows() == 0)
			{
				$this->sess_destroy();
				return FALSE;
			}

			// Is there custom data?  If so, add it to the main session array
			$row = $query->row();
			if (isset($row->user_data) AND $row->user_data != '')
			{
				$custom_data = $this->_unserialize($row->user_data);

				if (is_array($custom_data))
				{
					foreach ($custom_data as $key => $val)
					{
						$session[$key] = $val;
					}
				}
			}
		}

		if ($this->sess_use_azure === TRUE)
		{

			$filter = "RowKey eq '" . $session['session_id'] . "'";
			echo "READING". $session['session_id'];
			try {
				//echo $this->connection_string;
				$result = $this->tableRestProxy->queryEntities("sessiontable", $filter);
			}
			catch(ServiceException $e){
				// Handle exception based on error codes and messages.
				// Error codes and messages are here: 
				// http://msdn.microsoft.com/en-us/library/windowsazure/dd179438.aspx
				$code = $e->getCode();
				$error_message = $e->getMessage();
				echo "readerror";
				echo $code.": ".$error_message."<br />";
				if(!isset($result)) return FALSE;
			}

			$entities = $result->getEntities();
			//var_dump($entities);
			if (count($entities)== 0)
			{
				//$this->sess_tableDeleting();
				$this->sess_destroy();
				echo "0?";
				return FALSE;
			}			
			

			
			foreach($entities as $entity){
					$user_data = $this->_unserialize($entity->getPropertyValue('user_data'));
					$this->userdata['ip_address'] = $entity->getPropertyValue('ip_address');
					$this->userdata['user_agent'] = $entity->getPropertyValue('user_agent');
					$this->userdata['last_activity'] = $entity->getPropertyValue('last_activity');
					$this->userdata['user_data'] = $user_data;

				echo "property==";
				if (is_array($user_data))
				{
					foreach ($user_data as $key => $val)
					{
						$this->userdata[$key] = $val;
					}
				}
			}
			
			var_dump($this->userdata);
			echo "read";	
		}
			
		// Session is valid!
		$this->userdata = $session;
		unset($session);

		return TRUE;
	}

	function sess_write()
	{
		// Are we saving custom data to the DB?  If not, all we do is update the cookie
		if ($this->sess_use_database === FALSE)
		{
			$this->_set_cookie();
			return;
		}

		// set the custom userdata, the session data we will set in a second
		$custom_userdata = $this->userdata;
		$cookie_userdata = array();

		// Before continuing, we need to determine if there is any custom data to deal with.
		// Let's determine this by removing the default indexes to see if there's anything left in the array
		// and set the session data while we're at it
		foreach (array('session_id','ip_address','user_agent','last_activity') as $val)
		{
			unset($custom_userdata[$val]);
			$cookie_userdata[$val] = $this->userdata[$val];
		}

		// Did we find any custom data?  If not, we turn the empty array into a string
		// since there's no reason to serialize and store an empty array in the DB
		if (count($custom_userdata) === 0)
		{
			$custom_userdata = '';
		}
		else
		{
			// Serialize the custom data array so we can store it
			$custom_userdata = $this->_serialize($custom_userdata);
		}

		// Run the update query
		$this->CI->db->where('session_id', $this->userdata['session_id']);
		$this->CI->db->update($this->sess_table_name, array('last_activity' => $this->userdata['last_activity'], 'user_data' => $custom_userdata));

		if (count($this->userdata) > 0)
		{
			$result = $this->tableRestProxy->getEntity("sessiontable", "doublereg", $this->userdata['session_id']);
			$entity = $result->getEntity();
			echo "ok2";		
			$entity->setPropertyValue('last_activity', $this->now);
			$entity->setPropertyValue('user_data', $custom_userdata);
		}
		echo "test3";
		$this->_set_cookie($cookie_userdata);

		try{
			$this->tableRestProxy->updateEntity("sessiontable", $entity);
		}
		catch(ServiceException $e){
			// Handle exception based on error codes and messages.
			// Error codes and messages are here: 
			// http://msdn.microsoft.com/en-us/library/windowsazure/dd179438.aspx
			$code = $e->getCode();
			$error_message = $e->getMessage();
			echo $code.": ".$error_message."<br />";
		}
				
		// Write the cookie.  Notice that we manually pass the cookie data array to the
		// _set_cookie() function. Normally that function will store $this->userdata, but
		// in this case that array contains custom data, which we do not want in the cookie.
		$this->_set_cookie($cookie_userdata);
	}

	// --------------------------------------------------------------------

	/**
	 * Create a new session
	 *
	 * @access	public
	 * @return	void
	 */
	function sess_create()
	{
		$sessid = '';
		while (strlen($sessid) < 32)
		{
			$sessid .= mt_rand(0, mt_getrandmax());
		}

		// To make the session ID even more secure we'll combine it with the user's IP
		$sessid .= $this->CI->input->ip_address();

		$this->userdata = array(
							'session_id'	=> md5(uniqid($sessid, TRUE)),
							'ip_address'	=> $this->CI->input->ip_address(),
							'user_agent'	=> substr($this->CI->input->user_agent(), 0, 120),
							'last_activity'	=> $this->now,
							'user_data'		=> ''
							);


		// Save the data to the DB if needed
		if ($this->sess_use_database === TRUE)
		{
			$this->CI->db->query($this->CI->db->insert_string($this->sess_table_name, $this->userdata));
		}

		if ($this->sess_use_azure === TRUE)
		{
			$entity = new Entity();
			$entity->setPartitionKey("doublereg");
			echo $this->userdata['session_id'];
			$entity->setRowKey($this->userdata['session_id']);
			echo "DATA READING<br>";
			var_dump($this->userdata);
			echo "<br>";
			$entity->addProperty("session_id", EdmType::STRING, $this->userdata['session_id']);
			$entity->addProperty("ip_address", EdmType::STRING, $this->userdata['ip_address']);
			$entity->addProperty("user_agent", EdmType::STRING, $this->userdata['user_agent']);
			$entity->addProperty("last_activity", EdmType::INT32, $this->userdata['last_activity']);
			$entity->addProperty("user_data", EdmType::STRING, '');	
			try {
			// Create table.
				echo "test3";
				$this->tableRestProxy->insertEntity("sessiontable", $entity);
				//$this->tableRestProxy->createTable("sessiontable");
				//$this->sess_tableAdding();
				echo "test4";
				var_dump($this->userdata);
			}
			catch(ServiceException $e){
				$code = $e->getCode();
				$error_message = $e->getMessage();
				echo "create error";
				echo $code.": ".$error_message."<br />";				
				// Handle exception based on error codes and messages.
				// Error codes and messages can be found here: 
				// http://msdn.microsoft.com/en-us/library/windowsazure/dd179438.aspx
			}
		echo "create";			
		}
		
		
		// Write the cookie
		$this->_set_cookie();
	}	
}
?>
