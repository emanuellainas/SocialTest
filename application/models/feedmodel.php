<?php

class FeedModel extends CI_Model {

  function __construct() {
    // Call the Model constructor
    parent::__construct();
  }

  function generate_user_key( $group ) {
    $url = '/' . $this->config->item( 'listener_url' );
    $secret = $this->config->item( 'secret_string' );
    $remote_addr = $_SERVER[ 'REMOTE_ADDR' ];

    $expire = dechex( strtotime( "+2 hours" ) );
    $key = md5( $url . $secret . ':' . $remote_addr . ':' . $group . $expire );
    return $key . $expire;
  }
  
  function push_message( $user, $message, $id, $reply_to ) {
    $url = base_url() . $this->config->item( 'push_url' ) . '?channel=' . $user[ 'group_id' ];
    $this->load->model( 'User' );
    
    // Create generic data object and JSON it
    $data = new stdClass();
    $data->author = $user[ 'username' ];
    $data->message = $message;
    $data->id = $id;
    $data->timestamp = time();
    $data->avatar = $this->User->get_avatar( $user[ 'user_id' ] );
    // Post or comment?
    $data->type = 'post';
    if ( 0 != $reply_to ) {
      $data->type = 'comment';
      $data->reply_to = $reply_to;
    }
    // Encode it as a single item array for uniformization purposes
    $data = json_encode( array( $data ) );
    
    // cURL the data onto the push server
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POST, 1 );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $ret = curl_exec( $ch );
    curl_close( $ch );
    
    return $ret;
  }
  
  function save_message( $author, $message, $reply_to ) {
    $query = "INSERT INTO `messages` ( `user_id`, `reply_to`, `timestamp`, `message`, `group_id` )
              VALUES ( %d,  %d, '%s', '%s', %d )";
    $query = sprintf( $query,
                $author[ 'user_id' ],
                $reply_to,
                time(),
                mysql_real_escape_string( $message ),
                $author[ 'group_id' ]
            );
    $this->db->query( $query );
    $id = mysql_insert_id();
    
    // Push the values onto the Sphinx real-time index
    $link = mysql_connect( '127.0.0.1:9306', '', '', true );
    if ( false !== $link ) {
      $query = "INSERT INTO `rt` ( `id`, `user_id`, `reply_to`, `timestamp`, `message`, `group_id`)
                VALUES( %d, %d, %d, '%s', '%s', %d )";
      $query = sprintf( $query,
                  $id,
                  $author[ 'user_id' ],
                  $reply_to,
                  time(),
                  mysql_real_escape_string( $message ),
                  $author[ 'group_id' ]
              );
      mysql_query( $query, $link ) or die( mysql_error() );
      mysql_close( $link );
    }
    
    return $id;
  }
  
  // Retrieve comments for a specific post id
  function fetch_comments( $id ) {
    $query = "SELECT A.`id`, `message`, `timestamp`, B.`username` AS `author`, B.`avatar` FROM `messages` A
                LEFT JOIN `users` B ON B.`id` = A.`user_id`
              WHERE A.`reply_to` = %d
              ORDER BY A.`timestamp` ASC";
    $query = sprintf( $query, $id );
    
    $query = $this->db->query( $query );
    return $query->result();
  }
  
  // Return data as valid JSON
  function encode_data( $data ) {
    $data = json_encode( $data );
    
    // Sanitize some chatacters
    $data = str_replace( array( "'", '\"' ), array( "\'", '&quot;' ), $data );
    
    return $data;
  }
  
  function list_messages( $user, $order = 'desc', $filter = array() ) {
    $query = "SELECT A.`id`, `message`, `timestamp`, B.`username` AS `author`, B.`avatar` FROM `messages` A
                LEFT JOIN `users` B ON B.`id` = A.`user_id`
              WHERE %s A.`group_id` = %d AND A.`reply_to` = 0
              ORDER BY A.`timestamp` %s
              LIMIT 0, %d";
    
    // Only fetch certain ids
    $condition = '';
    if ( ! empty( $filter ) ) {
      $condition = ' A.`id` IN ( ' . implode( ',', $filter ). ' ) AND';
    }          
    
    $query = sprintf( $query, $condition, $user[ 'group_id' ], strtoupper( $order ), $this->config->item( 'feed_limit' ) );
    
    $data = array();
    $query = $this->db->query( $query );
    foreach ( $query->result() as $row ) {
      // Get comments
      $row->comments = $this->fetch_comments( $row->id );
      // Mark this type of items as posts
      $row->type = 'post';
      $data[] = $row;
    }
    // For compatibility with the ajax function
    $data = array_reverse( $data ); 
    
    if ( !empty( $filter ) ) {
      // Return without encoding
     return $data;
    }
    
    return $this->encode_data( $data );
  }
  
  // Get the message content for a single entry
  function get_message_content( $ids, $sphinx, $term ) {
    $query = "SELECT `message` FROM `messages` WHERE `id` IN ( %s ) ORDER BY `id` DESC";
    $query = sprintf( $query, implode( ',', $ids ) );
    $query = $this->db->query( $query );
    
    $content = array();
    foreach ( $query->result() as $row ) {
      $content[] = $row->message;
    }
    // Highlight words
    $highl = $sphinx->BuildExcerpts( $content, 
                                     $this->config->item( 'sphinx_main_index' ),
                                     $term,
                                     array(
                                        'before_match' => '<span class=highlight>',
                                        'after_match' => '</span>'
                                    ) );
    // QUICKFIX: if Sphinx cannot highlight replace with original content
    foreach( $highl as $i=>$text ) {
      if ( '' == $text ) {
        $highl[ $i ] = $content[ $i ];
      }
    }
    
    return $highl;
  }
  
  // Do a search query on the Sphinx server
  function sphinx_search( $term, $group_id ) {
    // Load the Sphinx API
	  $this->load->library( 'SphinxClient' );

    // Initiate the Sphinx client
    $cl = new SphinxClient();
    $host = $this->config->item( 'sphinx_host' );
    $port = $this->config->item( 'sphinx_port' );
    $cl->SetServer( $host, $port );
    $cl->SetConnectTimeout( 1 );
    $cl->SetArrayResult( true );
    
    // Set options
    $cl->SetMatchMode( SPH_MATCH_ALL );
    $cl->SetSortMode( SPH_SORT_ATTR_DESC, 'timestamp' );
    $cl->SetLimits( 0, $this->config->item( 'feed_limit' ) );
    
    // Limit results to current user group
    $cl->SetFilter( 'group_id', array( $group_id ) );
    
    $res = $cl->Query ( $term, "*" );
    return array( $res, $cl );
  }
  
  // Sort post array by timestamp function
  static function compare_posts( $a, $b ) {
    if ( $a->timestamp == $b->timestamp )
      return 0;
    return ( $a->timestamp > $b->timestamp ) ? 1 : -1; 
  }
  
  // Search data using the Sphinx server
  function search_messages( $term, $user ) {
    // Sphinx query
    list( $res, $cl ) = $this->sphinx_search( $term, $user[ 'group_id' ] );

    $data = array(); $out = array();
    if ( false !== $res ) {
      // Valid results
      if ( isset( $res[ 'matches' ] ) ) {
        $this->load->model( 'User' );
        
        // Loop once and build an ID array
        // Also, group comments by parent ID
        $ids = array();
        $comments = array();
        foreach( $res[ 'matches' ] as $result ) {
          $ids[] = $result[ 'id' ];

          $comment_parent = $result[ 'attrs' ][ 'reply_to' ];
          $comments[ $comment_parent ][] = $result[ 'id' ];
        }
        // Retrieve actual content
        $content = $this->get_message_content( $ids, $cl, $term );
        
        // Main loop
        foreach( $res[ 'matches' ] as $i=>$result ) {
          $attrs = $result[ 'attrs' ];
          // Handle posts
          if ( 0 == $attrs[ 'reply_to' ] ) {
            // Get user info
            $user = $this->User->fetch_user_info( $attrs[ 'user_id' ] );

            // Build object and append it to the data array
            $obj = new stdClass();
            $obj->author = $user[ 'username' ];
            $obj->message = $content[ $i ];
            $obj->id = $result[ 'id' ];
            $obj->timestamp = $attrs[ 'timestamp' ];
            $obj->avatar = $user[ 'avatar' ];
            $obj->type = 'post';
            // Get comments
            $obj->comments = $this->fetch_comments( $obj->id );
            $data[ $obj->id ] = $obj;
          }
          else {
            // Associate content with comment id
            $messages[ $result[ 'id' ] ] = $content[ $i ];
          }
        }
        
        // Handle comments
        if ( ! empty( $comments ) ) {
          // Fetch comment associated posts and siblings
          $comment_data = $this->list_messages( $user, 'desc', array_keys( $comments ) );
          
          foreach ( $comment_data as $i=>$post ) {
            // Replace comment content with highlighted content
            foreach ( $post->comments as $j=>$comment ) {
              if ( isset( $messages[ $comment->id ] ) ) {
                $comment_data[ $i ]->comments[ $j ]->message = $messages[ $comment->id ];
              }
            }
            // Make sure posts withs results in both content and comments
            // don't show up two times
            if ( isset( $data[ $post->id ] ) ) {
              $data[ $post->id ]->comments = $post->comments;
              unset( $comment_data[ $i ] );
            }
          }
          
          // Merge with initial posts and sort by timestamp
          $out = array_merge( $data, $comment_data );
          usort( $out, array( $this , "compare_posts" ) );
        }
      }
    }
    return $this->encode_data( $out );
  }
  
  // Simulate a browser request
  function browser_request( $url ) {
    $ch = curl_init();
    // Set URL and other appropriate options
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_HEADER, 0);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
    curl_setopt( $ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.13) Gecko/20101203 Firefox/3.6.13" );
    curl_setopt( $ch, CURLOPT_ENCODING, 'gzip,deflate' );
    curl_setopt( $ch, CURLOPT_HEADER, false );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
      "Accept-Language: en-us,en;q=0.5"
    ));
    $ret = curl_exec( $ch );
    curl_close( $ch );
    return $ret;
  }
}

?>