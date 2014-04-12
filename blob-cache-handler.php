<?php
$path = dirname(__FILE__) . '\library\dependencies';

require_once 'library/WindowsAzure/WindowsAzure.php';

set_include_path(get_include_path() . PATH_SEPARATOR . $path);

use WindowsAzure\Common\ServicesBuilder;

class PN_Blob_Cache_Handler {

	private $remote_cache_endpoint = null;

	public function __construct( ) {

	}

	public function pn_blob_cache_set( $key, $data, $expire, $account_name, $account_key, $container ) {
		$connection_string = 'DefaultEndpointsProtocol=http;AccountName=' . $account_name . ';AccountKey=' . $account_key;

		$blobRestProxy = ServicesBuilder::getInstance()->createBlobService( $connection_string );

		try {
			//Upload blob
			$blobRestProxy->createBlockBlob($container, $key, $data);
			$blobRestProxy->setBlobMetadata($container, $key, array( 'Projectnamicacheduration' => $expire) );
			return true;
		} catch(ServiceException $e){
			$code = $e->getCode();
			$error_message = $e->getMessage();
			echo $code.": ".$error_message."<br />";
			return false;
		}

	}

	public function pn_blob_cache_get( $key, $account_name, $account_key, $container ) {
		$connection_string = 'DefaultEndpointsProtocol=http;AccountName=' . $account_name . ';AccountKey=' . $account_key;

		$blobRestProxy = ServicesBuilder::getInstance()->createBlobService( $connection_string );

		$blob = $blobRestProxy->getBlob( $container, $key );

		$blob_contents = stream_get_contents( $blob->getContentStream() );

		return $blob_contents;
	}
}

?>
