<?php

class User extends CI_Model {

  function __construct() {
    // Call the Model constructor
    parent::__construct();
  }
  
  // Get current user data from the session
  function current_user() {
    $user_data = $this->session->userdata;
    // Set a default user if none exists
    if ( ! isset( $user_data[ 'user_id' ] ) || ! is_numeric( $user_data[ 'user_id' ] )
        || ! isset( $user_data[ 'avatar' ] )) {
      // Default user ID
      $defult_id = 1;
      $user_data = $this->fetch_user_info( $defult_id );
    }
    return $user_data;
  }
  
  // Return information about a specific user
  // and store it into the session
  function fetch_user_info( $id ) {
    $query = "SELECT `username`, `group_id`, `group_name`, `avatar` FROM `users`
              WHERE `id` = %d";
    $query = sprintf( $query, $id );

    $query = $this->db->query( $query );
    if ($query->num_rows() > 0) {
       $row = $query->row();
       
       $row->user_id = $id;
       $this->session->set_userdata( $row );
       return $this->session->userdata;
    }
    return false;
  }
  
  // Get a list of all the users
  function fetch_list() {
    $query = "SELECT `id`, `username`, `group_name` FROM `users`";
    $query = $this->db->query( $query );
    foreach ( $query->result() as $row ) {
      $data[] = $row; 
    }
    return $data;
  }
  
  // Validate user id
  function valid_user_id( $id ) {
    $query = "SELECT `id` FROM `users` WHERE `id` = %d";
    $query = sprintf( $query, $id );
    $query = $this->db->query( $query );
    if ($query->num_rows() > 0) {
      return true;
    }
    return false;
  }
  
  // Get a user's avatar
  function get_avatar( $id ) {
    $query = "SELECT `avatar` FROM `users` WHERE `id` = %d";
    $query = sprintf( $query, $id );
    $query = $this->db->query( $query );
    if ($query->num_rows() > 0) {
       $row = $query->row();
       return $row->avatar;
    }
    return null;
  }

}

?>