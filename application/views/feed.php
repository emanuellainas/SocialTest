<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
    <head>
      <title>Feed</title>
      <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.0/jquery.min.js"></script>
      <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>app/static/js/ajax.js"></script>
      <script type="text/javascript" src="<?php echo base_url(); ?>app/static/js/other.js"></script>
      <link type="text/css" rel="stylesheet" href="<?php echo base_url(); ?>app/static/css/style.css" />
      <script>
        var base_url = '<?php echo base_url(); ?>';
        var avatar = '<?php echo base_url() . "app/static/img/" . $avatar; ?>';
        var listener_url = '<?php echo $listener_url; ?>';
        var channel = '<?php echo $group; ?>';
        var key = '<?php echo $key; ?>';
      </script>
    </head>
    <body>
    <div id="container">
      <h1>Welcome to this awesome social app!</h1>
      <div class="button switch_user">
        Switch user
        <ul id ="user_box">
          <?php foreach ( $users as $user ): ?>
            <?php $class = ''; ?>
            <?php if ( $user->id == $curren_uid ) $class = 'selected'; ?>
            <li class="<?php echo $class; ?>">
              <a href="<?php echo base_url(); ?>app/feed/switch_user/<?php echo $user->id; ?>">
                <?php echo $user->username; ?> (<i><?php echo $user->group_name; ?></i>)
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div id="menu">
        <form id="search-form" action="<?php echo base_url(); ?>app/feed/search/" />
          Search posts: <input type="text" class="search" name="search" value="random thoughts" />
        </form>
      </div>
      <div id="status-update">
        <form id="status" method="POST" action="<?php echo base_url() . 'app/feed/push'; ?>">
          <input type="text" class="text-input" name="update" value="Care to share with us?" />
        </form>
      </div>
      <div id="sort"><div class="icon"></div></div>
      <div id="feed-holder">
      </div>
    </div>
    <script>
      var feed_items = '<?php echo $feed; ?>';
    </script>
    </body>
</html>