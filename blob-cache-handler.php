<?php
$path = dirname(__FILE__) . '\library\dependencies';

require_once 'library/WindowsAzure/WindowsAzure.php';

set_include_path(get_include_path() . PATH_SEPARATOR . $path);

use WindowsAzure\Common\ServicesBuilder;
use WindowsAzure\Blob\Models\CreateBlobOptions;
use WindowsAzure\Blob\Models\ListBlobsOptions;

class PN_Blob_Cache_Handler {

	private $account_name;
    private $account_key;
    private $container;
    private $connection_string;
    private $blob_service;
    private $remote_cache_endpoint = NULL;
	private $content_type = NULL;
	private $headers_as_json;

	public function __construct( ){
        $this->account_name = getenv("ProjectNamiBlobCache.StorageAccount");
        $this->account_key = getenv("ProjectNamiBlobCache.StorageKey");
        $this->container = getenv("ProjectNamiBlobCache.StorageContainer");		
        $this->connection_string = 'DefaultEndpointsProtocol=http;AccountName=' . $this->account_name . ';AccountKey=' . $this->account_key;
        $this->blob_service = ServicesBuilder::getInstance()->createBlobService( $this->connection_string );
	}

	/*
	* This takes an array of headers and prepares the data as an array of json data objects containing name & value properties
	* It also looks for the "Content-Type" declared in the header array so we can set that value for the blob
	*
	* SETS
	* $this->content_type
	* $this->headers_as_json
	*/
	public function prepare_headers( $headers ){
		if( isset( $headers ) ){
		    foreach( $headers as $x ){
		        $name_val_pair = explode( ": ", $x );
		
		        $header = (object) array( 'name' => trim( $name_val_pair[0] ), 'value' => trim( $name_val_pair[1] ) );
		
		        if( strtolower( $header->name ) == "content-type" ){
		            $content_type_parts = explode( "; ", $header->value );
		
		            $this->content_type = $content_type_parts[0];
		        }
		        $result[] = $header;
		    }            
		    $this->headers_as_json = json_encode( $result );
		
		    return TRUE;
		}
		return FALSE;
	}

	public function pn_blob_cache_set( $key, $data, $expire, $headers ) {

	    $options = new CreateBlobOptions();
	
	    //Set metadata and content-type for blob if header array was provided 
	    if( $this->prepare_headers( $headers ) ){                                   
	        if( isset( $this->content_type )){
	            $options->setBlobContentType( $this->content_type );
	        }
	        $options->setCacheControl( "max-age=".$expire );
	        $options->setMetadata( array( 'Projectnamicacheduration' => $expire, 'Headers' => $this->headers_as_json ) );
	    }
	    //If we don't have header info, we will only set the cache expiration
	    else{
	        $options->setMetadata( array( 'Projectnamicacheduration' => $expire ) );
	    }
	
		try {
			//Upload blob            
			$this->blob_service->createBlockBlob($this->container, $key, $data, $options);            
			return true;
		} 
	    catch(ServiceException $e){
            $this->pn_handle_exception( $e );
		}

	}

	public function pn_blob_cache_get( $key ){

		$blob = $this->blob_service->getBlob( $this->container, $key );

		$blob_contents = stream_get_contents( $blob->getContentStream() );

		return $blob_contents;
	}

    public function pn_blob_cache_del( $key ){
        if ( $this->pn_blob_cache_exists( $key ) ){
            try{            
                $this->blob_service->deleteBlob( $this->container, $key );
                return TRUE;                           
            }        
            catch( ServiceException $e ){        
                $this->pn_handle_exception( $e );               
            }
        }
        return FALSE;
    }

    public function pn_blob_cache_exists( $key ){
        try{
            $blob_list_options = new ListBlobsOptions();
            $blob_list_options->setPrefix( $key );
            $blob_list = $this->blob_service->listBlobs( $this->container, $blob_list_options );

            $blobs = $blob_list->getBlobs();

            foreach($blobs as $blob){                
                if( $blob->getname() == $key ){
                    return TRUE;
                }
            }            
        }
        catch( ServiceException $e ){                    
            $this->pn_handle_exception( $e );
        }
        return FALSE;
    }

    public function pn_handle_exception( $e ){
        $code = esc_html( $e->getCode() );
        $error_message = esc_html( $e->getMessage() );
        echo $code.": ".$error_message."<br />";
    }

}

?>
