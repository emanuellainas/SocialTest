<?php

class Feed extends CI_Controller {

	public function index() {

	  // Current user data
	  $this->load->model( 'User' );
	  $user = $this->User->current_user();
	  
	  $this->load->model( 'FeedModel' );
	  $key = $this->FeedModel->generate_user_key( $user[ 'group_id' ] );
	  
	  // Get feed
	  $feed = $this->FeedModel->list_messages( $user );
	  
	  // Get all users (switch user functionality)
	  $users = $this->User->fetch_list();
	  
	  // Load template variables and feed tempalte
	  $data[ 'key' ] = $key;
	  $data[ 'listener_url' ] = base_url() . $this->config->item( 'listener_url' );
	  $data[ 'group' ] = $user[ 'group_id' ];
	  $data[ 'feed' ] = $feed;
	  $data[ 'users' ] = $users;
	  $data[ 'curren_uid' ] = $user[ 'user_id' ];
	  $data[ 'avatar' ] = $user[ 'avatar' ];
	  
		$this->load->view( 'feed', $data );
	}
	
	public function push() {
	  // Push new data
	  $update = $this->input->post( 'update' );
	  if ( $update && '' != trim( $update ) ) {
	    // Sanitize message
	    $message = strip_tags( trim( $update ) );
	    
  	  // User data
  	  $this->load->model( 'User' );
  	  $user = $this->User->current_user();
	    
	    $this->load->model( 'FeedModel' );
	    
	    $reply_to = 0;
	    // Is this a comment?
	    if ( isset( $_POST[ 'reply_to' ] ) && is_numeric( $_POST[ 'reply_to' ] ) ) {
	      $reply_to = intval( $_POST[ 'reply_to' ] );
	    }

	    // Save message into the database
	    $id = $this->FeedModel->save_message( $user, $message, $reply_to );
	    // Send it to the Nginx push server
	    $ret = $this->FeedModel->push_message( $user, $message, $id, $reply_to );

      if ( '' != trim( $ret ) ) {
        exit( '1' );
      }
	  }
	}
	
	public function search() {
	  // Check for search tesrm
    $term = $this->input->post( 'term' );
	  if ( $term && '' != trim( $term ) ) {
  	  // User data
  	  $this->load->model( 'User' );
  	  $user = $this->User->current_user();
	    
	    $this->load->model( 'FeedModel' );
	    print_r( $this->FeedModel->search_messages( $term, $user ) );
    }
	}
	
	public function switch_user() {
	  // Switch current user
	  $id =  $this->uri->segment( 3 );
	  if ( is_numeric( $id ) ) {
	    $this->load->model( 'User' );
	    if ( $this->User->valid_user_id( $id ) ) {
	      $this->User->fetch_user_info( $id );
	    }
	  }
	  header( "Location: " . base_url() . 'app/' );
	}
	
	public function sort() {
	  $order =  $this->uri->segment( 3 );
	  if ( in_array( $order, array( 'asc', 'desc' ) ) ) {
	    
  	  // Current user data
  	  $this->load->model( 'User' );
  	  $user = $this->User->current_user();
	    
	    // Sort the actual feed
	    $this->load->model( 'FeedModel' );
	    $feed = $this->FeedModel->list_messages( $user, $order );
	    echo $feed;
	    return;
	  }
	}
  
  // Get URL thumbail
  function get_thumbnail( $url, $html ) {
    // Handle videos
    $yt = preg_match( '/(http:\/\/|https:\/\/)?(www\.)?youtube.com\/watch\?v=([^&]+)/', $url, $matches );
    if ( $yt ) {
      $video = $matches[ count( $matches ) - 1 ];
      $thumb = 'http://i4.ytimg.com/vi/' . $video . '/default.jpg';
      return $thumb;
    }
    $vm = preg_match( '/(http:\/\/|https:\/\/)?(www\.)?vimeo.com\/(\d+)/', $url, $matches );
    if ( $vm ) {
      $video = $matches[ count( $matches ) - 1 ];
      $api = file_get_contents( 'http://vimeo.com/api/v2/video/' . $video . '.json' );
      $json = json_decode( $api );
      if ( $json ) {
        $thumb = $json[ 0 ]->thumbnail_medium;
        return $thumb;
      }
    }
    
    // Get images from the current page
    preg_match_all( '/src=[\'"]?([^\'" >]+[\.]+[(png|jpg|jpeg)]+)[\'" >]/', $html, $images, PREG_SET_ORDER );

		foreach ( $images as $key=>$image ) {
		  $thumb = $image[ 1 ];
			if ( FALSE === strpos( $thumb ,'http://' ) || FALSE === strpos( $thumb, '//' ) ) {
			  if ( substr( $url, -1, 1 ) != '/' && substr( $thumb, 0, 1 ) != '/' )
			    $thumb = $url . '/' . $thumb;
			  else
			    $thumb = $url . $thumb;
			}
			
			// Check for image size
			$size = @getimagesize( $thumb ); 
			if ( isset( $size[0] ) && isset( $size[1] ) ) {
			  // Calculate image ratio
				$w = $size[ 0 ];
				$h = $size[ 1 ];
				$ratio = ( $w > $h ) ? $w / $h : $h / $w;
				// Specific parameters for a proper thumbnail
				if ( $ratio >= 1 && $ratio < 1.8 && $w > 100 ) {
					return $thumb;
				}
			}
		}
		return false;
  }
  
  // Check for URLs and extract info about the first
  public function handle_urls() {
    
    if ( isset( $_POST[ 'status' ] ) && '' != $_POST[ 'status' ] ) {
    
      $message = $_POST[ 'status' ];
      
      $pattern = '/(http:\/\/|https:\/\/)?[a-z0-9\-\.]+\.[a-z]{2,3}(\/)?\S*/i';
      preg_match( $pattern, $message, $matches );
      if ( count( $matches ) ) {
        $url = $matches[ 0 ];
        // Add protocol if missing
        if ( ! isset( $matches[ 1 ] ) || ! in_array( $matches[ 1 ], array( 'http://', 'https://' ) ) ) {
          $url = 'http://' . $url;
        }
        $this->load->model( 'FeedModel' );
        $html = $this->FeedModel->browser_request( $url );
      
        // Match the URL title
        $title = '';
        if ( $html ) {
          preg_match( '/<\s*title\s*>([^<]+)<\s*\/\s*title\s*>/i', $html, $matches );
          if ( count( $matches ) ) {
            $title = str_replace( array( "\r", "\n" ), array( '',  '' ), $matches[ 1 ] );
          }
        }
      
        // Find description
        $meta = @get_meta_tags( $url );
        $description = $url;
        if ( isset( $meta[ 'description' ] ) ) {
          $description = trim( $meta[ 'description' ] );
        }
      
        // Try to find a suitable thumbnail
        $thumb = $this->get_thumbnail( $url, $html );
      
        // Build object
        $data = new stdClass();
        $data->title = $title;
        $data->description = $description;
        $data->url = $url;
        if ( $thumb )
          $data->thumb = $thumb;
      
        echo json_encode( $data );
      }
    }
  }
}

?>