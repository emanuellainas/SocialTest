// Initialize etag and last modified headers with current values
var etag = 0;
var d = new Date();
last_mod = d.toGMTString();

// Do a long poll on the Nginx server
function listen() {
  $.ajax({
    type: 'GET',
    url: listener_url + '?channel=' + channel + '&key=' + key,
    beforeSend: function( xhr ) {
      // Set the headers
      xhr.setRequestHeader("If-None-Match", etag);
      xhr.setRequestHeader("If-Modified-Since", last_mod);
    },
    cache: false,
    success: function(data, status, xhr) {
      // Update etag and last modified headers
      etag = xhr.getResponseHeader('Etag');
      last_mod = xhr.getResponseHeader('Last-Modified');
      
      // Do fancy things with the data
      add_feed_items( data, true );
      
      // Initialize another request
      listen();
    },
    dataType: 'text'
  });
}

// Comment HTML template
function comment_template( c_obj ) {
  var comment = '' + 
    '<div class="comment">' +
      '<div class="avatar"><img src="' + base_url + 'app/static/img/' + c_obj.avatar + '" /></div>' +
      '<div class="text">' +
        '<span class="author">' + c_obj.author + '</span> ' +
        c_obj.message +
        '<div class="more">' +
          '<input type="hidden" value="' + c_obj.timestamp + '" />' +
          '<span class="time-posted">a few</span> ' +
          '<span class="time-units">seconds</span> ago' +
        '</div>' +
      '</div>' +
      '<div class="clear"></div>' +
    '</div>';
  return comment;
}

function add_feed_items( json_data, via_poll ) {
  var obj = jQuery.parseJSON( json_data );
  if ( 'object' == typeof( obj ) ) {
    for ( var i = 0; i < obj.length; i++ ) {

      // Handle new posts
      if ( 'post' == obj[ i ]. type ) {
        
        // If the feed is sorted ascending, do not prepend elements
        if ( via_poll && $( '#sort .icon' ).hasClass( 'rev' ) ) {
          continue;
        }
        
        // Build comments string
        var comments = '';
        if ( obj[ i ].hasOwnProperty( 'comments' ) ) {
          var c_obj = obj[ i ].comments;
          if ( c_obj.length ) {
            for ( var j = 0; j < c_obj.length; j++ ) {
              comments += comment_template( c_obj[ j ] );
            }
          }
        }
      
        // Feed item template
        var item = $(
          '<div class="item">' +
            '<div class="avatar"><img src="' + base_url + 'app/static/img/' + obj[ i ].avatar + '" /></div>' + 
            '<div class="post">' + 
              '<div class="author">' + obj[ i ].author + '</div>' + 
              '<div class="message">' + obj[ i ].message + '</div>' +
              '<div class="more">'+
                '<input type="hidden" value="' + obj[ i ].timestamp + '" />' +
                '<span class="time-posted">a few</span> ' +
                '<span class="time-units">seconds</span> ago' +
              '</div>' +
              '<div class="comments">' + comments +
                '<div class="add-comment">' +
                  '<input type="hidden" name="reply-to" value="' + obj[ i ].id + '" />' +
                  '<input type="text" value="Comment all you like..." name="add-comment" class="comment-input" />' +
                  '<div class="clear"></div>' +
                '</div>' +
              '</div>' +
            '</div>' +
            '<div class="clear"></div>' +
          '</div>'
        );
        $( '#feed-holder').prepend( item );
      }
      else if ( 'comment' == obj[ i ].type ) {
        // Add the comment to the corresponding POST if it exists on page
        var selector = '.add-comment input[ name="reply-to" ][ value="' + obj[ i ].reply_to + '" ]';
        if ( $( selector ).length ) {
          var item = $( comment_template( obj[ i ] ) );
          item.insertBefore( $( selector ).parent() );
        }
      }
      
      // Create a nice highlight effect for the new post
      if ( via_poll && 'undefined' != typeof( item ) ) {
        item.effect( "highlight", {}, 5000 );
      }
    }
    // Start post timer
    if ( ! via_poll ) {
      ticker();
      setInterval( "ticker()", 1000 );
    }
  }
}

$(document).ready(function() {
  // Display "static" feed items
  add_feed_items( feed_items, false );
  
  // Start listening. Use a timeout to prevent browser loading indicator.
  setTimeout( "listen()", 300 );
  
  // Handle form POST via Ajax
  $( '#status' ).submit(function() {
    $.post( $(this).attr( 'action' ), $(this).serialize(), function( data ) {
      if ( '1' == data ) {
        // Completed succesfully!
        var input = $( '#status-update .text-input' );
        input.val('');
        input.trigger( 'blur' );
      }
    });
    return false;
  });
  
  // Handle comment POST via Ajax
  $( '.comment-input' ).live( 'keypress', function( e ) {
    var code = (e.keyCode ? e.keyCode : e.which);
    // Enter key was pressed
    if ( 13 == code ) {
      // Same action as status form
      var action = $( '#status' ).attr( 'action' );
      // Associated post id
      var post_id = $(this).parent().find( 'input[ name = "reply-to"] ' ).val();
      var input = $(this);
      $.post( action, { update: $(this).val(), reply_to: post_id }, function( data ) {
        if ( '1' == data ) {
          // Completed succesfully!
          input.val('');
          input.trigger( 'blur' );
        }
      });
    }
  });
});