var input_holder = null;
var status_area = '<textarea class="text-input area" name="update"></textarea><div id="status-bar"><input class="submit" type="submit" value="Update my status" /><div class="clear"></div></div>';
var timout, interval;
var xhr;
var status_text = '';
var save_feed_content = '';

// Site preview Ajax call
function link_preview() {
  // Cancel previous requests
  if ( 'undefined' != typeof( xhr ) ) {
    xhr.abort();
  }
  
  // Show loading animation
  clearInterval( interval );
  $( '#status-update .preview' ).remove();
  $( '<div class="preview">Loading...</div>' ).insertBefore( '#status-bar' );
  
  xhr = $.ajax({
    type: 'POST',
    url: base_url + 'app/feed/handle_urls/',
    cache: true,
    data: { status: $('#status-update .text-input').val() },
    timeout: 18000,
    success: function(data) {
      if ( 'undefined' != typeof( data ) && null != data && data.hasOwnProperty( 'title' ) && '' != data.title ) {
        var thumb = '';
        if ( data.thumb ) {
          thumb = '<a href="' + data.url + '" target="_blank">' + 
                    '<img class="preview-thumb" src="' + data.thumb + '" />'+
                  '</a>';
        }
        // Create the preview panel
        var preview = 
        '<div class="preview">' + thumb +
          '<div class="info">' +
            '<a href="' + data.url + '" target="_blank">' + data.title + '</a>' +
            '<div class="description">' + data.description + '</div>' +
          '</div>' +
          '<div class="delete">X</div>' +
          '<div class="clear"></div>' +
        '</div>'
        $( '#status-update .preview' ).remove();
        $( preview ).insertBefore( '#status-bar' );
        if ( '' == thumb ) {
          $( '#status-update .preview .info' ).width( 380 );
        }
        else {
          $( '#status-update .preview .info' ).width( 245 );
        }
        
        // Delete button
        $( '#status-update .preview .delete' ).unbind( 'click' ).bind( 'click', function() {
          $( '#status-update .preview' ).remove();
        });
      }
      else {
        // Clear loading message
        $( '#status-update .preview' ).remove();
      }
      
      // Resume link checking
      interval = setInterval( "check_for_urls()", 500 );
    },
    error: function() {
      // Clear loading message
      $( '#status-update .preview' ).remove();
      // Resume link checking
      interval = setInterval( "check_for_urls()", 500 );
    },
    dataType: 'json'
  });
}

// Check for URLs inside the status text
function check_for_urls() {
  var text = $('#status-update .text-input').val();
  if ( text != status_text ) {
    status_text = text;
    
    // Cancel previous requests
    if ( 'undefined' != typeof( xhr ) ) {
      xhr.abort();
    }
    
    if( text.match( /(http:\/\/|https:\/\/)?[a-z0-9\-\.]+\.[a-z]{2,3}(\/)?\S*/i ) ) {
      if ( 'undefined' != typeof( timeout ) ) {
        clearTimeout( timeout );
      }
      timeout = setTimeout( "link_preview()", 1600 );
    }
  }
}

// Toggle between an input and a textarea
function do_element_focus() {
  $('#status-update .text-input').focus(function() {
    if ( 'Care to share with us?' == $(this).val() ) {
      input_holder = $(this);
      $(this).replaceWith( status_area );
      $('#status-update .text-input').focus();
      do_element_blur();
      
      // Handle status links
      interval = setInterval( "check_for_urls()", 500 );
    }
  });
}

// Switch back to the textarea
function do_element_blur() {
  $( '#status-update .text-input' ).unbind( 'blur' ).blur(function() {
    if ( '' == $(this).val() ) {
      $(this).replaceWith( input_holder );
      $( '#status-update .preview' ).remove();
      $( '#status-bar' ).remove();
      do_element_focus();
      clearInterval( interval );
    }
  });
}

// Comment input focus / blur handling
function comment_focus() {
  $( '.comment-input' ).live( 'focus', function() {
    if ( 'Comment all you like...' == $(this).val() ) {
      $(this).val( '' );
      var pic = $( '<div class="avatar"><img src="' + avatar + '" /></div>' );
      pic.insertBefore( $(this) );
      $(this).addClass( 'marg-right' );
      var padd = parseInt( pic.css( 'padding-right' ) ) + parseInt( pic.css( 'padding-left' ) ) + parseInt( $(this).css( 'margin-right' ) );
      $(this).width( $(this).width() - pic.width() - padd );
    }
  });
  $( '.comment-input' ).live( 'blur', function() {
    if ( '' == $(this).val() ) {
      $(this).val( 'Comment all you like...' );
      var pic = $(this).parent().find( '.avatar' );
      var padd = parseInt( pic.css( 'padding-right' ) ) + parseInt( pic.css( 'padding-left' ) ) + parseInt( $(this).css( 'margin-right' ) );
      $(this).width( $(this).width() + pic.width() +  padd );
      $(this).removeClass( 'marg-right' );
      pic.remove();
    }
  });
}

// Calculate feed item time
function ticker() {
  var now = Math.round( ( new Date() ).getTime() / 1000 );
  $( '#feed-holder .item .post > .more, #feed-holder .item .comment .text > .more' ).each( function(index) {
    var time_posted = Number( $(this).find( '> input' ).val() );
    var diff = now - time_posted;
    var time = $(this).find( '> .time-posted' );
    var units = $(this).find( '> .time-units' );
    if ( diff < 60 ) {
      time.text( 'a few' );
    }
    else if ( diff < 60 * 60 ){
      time.text( Math.floor( diff / 60 ) );
      units.text( 'minutes' );
    }
    else if ( diff < 60 * 60 * 24 ){
      time.text( Math.floor( diff / 60 / 60 ) );
      units.text( 'hours' );
    }
    else {
      time.text( Math.floor( diff / 60 / 60 / 24 ) );
      units.text( 'days' );
    }
  });
}

// Display JSON data results
function display_data( data ) {
  feed_items = data.replace( /\\/g, "" ); // unescape
  $( '#feed-holder').html('');
  add_feed_items( feed_items, false );
}

$(document).ready(function() {
  // Status update input handling
  do_element_focus();
  
  // Comment input handling
  comment_focus();
  
  // Handle user switch button
  $( '.switch_user' ).click(function(e) {
    $( '#user_box' ).css( 'display', 'block' );
    e.stopPropagation();
  });
  $( 'body' ).click(function() {
    $( '#user_box' ).css( 'display', 'none' );
  });
  
  // Search input
  $( '#menu input' ).bind( 'focus', function() {
    if ( 'random thoughts' == $(this).val() ) {
      $(this).val('');
    }
  });
  $( '#menu input' ).bind( 'blur', function() {
    if ( '' == $(this).val() ) {
      $(this).val( 'random thoughts' );
    }
  });
  
  // Sort button
  $( '#sort' ).click(function() {
    var icon = $(this).find( '.icon' );
    var order = 'asc';
    if ( icon.hasClass( 'rev' ) ) {
      order = 'desc';
    }
    
    $.get( base_url + 'app/feed/sort/' + order + '/', function(data) {
      display_data( data );
      icon.toggleClass( 'rev' );
    },
    'text' );
  });
  
  // Handle search functionality
  $( '#search-form input' ).bind( 'keypress', function( e ) {
    var code = (e.keyCode ? e.keyCode : e.which);
    // Enter key was pressed
    if ( 13 == code ) {
      var action = $(this).parent().attr( 'action' );
      if ( '' != $(this).val() ) {
        $( '#sort' ).css( 'visibility', 'hidden' );
        $.post( action, { term: $(this).val() }, function( data ) {
          // Store the original feed content
          if ( '' == save_feed_content ) {
            save_feed_content = $( '#feed-holder' ).html();
          }
          if ( '[]' != data) {
            // Display search results
            display_data( data );
          }
          else {
            // No results found
            $( '#feed-holder' ).html( '<span class="oops">Ooops! The term could not be found! Try again?</span>' );
          }
        });
      }
      else {
        $( '#sort' ).css( 'visibility', '' );
        // Restore original feed content
        $( '#feed-holder').html( save_feed_content );
        // Remove any status highlights
        $( '#feed-holder .item' ).removeAttr( 'style' );
        save_feed_content = '';
      }
      e.preventDefault();
    }
  });
});