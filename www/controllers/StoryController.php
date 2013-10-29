<?php

class StoryController {

  public static function add() {

		$row = getDatabase()->one(" select * from people where id = :id ",array('id'=>getSession()->get('user_id')));
		if (!$row['author']) {
			top();
			print "ERROR: you do not have authorship rights\n";
			bottom();
			return;
		}

    $story = array();
    $story['personid'] = getSession()->get('user_id');
    $id = db_insert('story',$story);

    header("Location: edit/$id");
  }

  public static function show($id,$restTitle) {
    $story = getDatabase()->one(" 
      select 
        s.title,s.body,
        p.name as author,
        left(s.updated,16) updated,
        s.published,s.deleted,
        p.*
      from story s
        join people p on p.id = s.personid
      where 
        s.id = :id 
      ",array('id'=>$id));

    # disallow linking to /story/X/WHATEVER_SOMEONE_TYPED by redirecting if the supplied
    # "url_title" does not match the actual story title. Also helps if story title was 
    # changed but links are already out there on social media
    # title - urlencode - removed junk - lowercase
    $titleToUrlPart = $story['title'];
    $titleToUrlPart = preg_replace('/ /','-',$titleToUrlPart);
    $titleToUrlPart = urlencode($titleToUrlPart);
    $titleToUrlPart = preg_replace('/%../','',$titleToUrlPart);
    $titleToUrlPart = strtolower($titleToUrlPart);
    $storyUrl = OttWatchConfig::WWW."/story/{$id}/{$titleToUrlPart}";
    if ($titleToUrlPart != $restTitle) {
      # new title, or someone is having fun
      header("Location: $storyUrl");
      return;
    }

    top($story['title']);

    if ($story['deleted'] == 1) {
      ?>
      <h1>Error: this story has been deleted</h1>
      <?php
      bottom();
      return;
    }
    if ($story['published'] == 0) {
      ?>
      <h1>Error: this story is not yet published</h1>
      <?php
      bottom();
      return;
    }
    ?>
    <div class="row-fluid">
    <div class="offset4 span4">
    <h1 id="previewtitle"><?php print "{$story['title']}\n"; ?></h1>
    <p style="float: right; text-align: right;">
    <b><?php print $story['author']; ?></b><br/>
    <?php print $story['updated']; ?>
    </p>
    <p>
    <div class="fb-like" 
      data-href="<?php print $storyUrl; ?>" 
      data-width="The pixel width of the plugin" 
      data-height="The pixel height of the plugin" 
      data-colorscheme="light" 
      data-layout="button_count" 
      data-action="like"
      data-show-faces="false" 
      data-send="false"></div>
		<a href="https://twitter.com/share" class="twitter-share-button" data-via="OttWatch" data-related="ottwatch" data-hashtags="ottpoli">Tweet</a>
		<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
    </p>
    <p style="border-top: 1px solid #f0f0f0; clear: both;"><?php print $story['body']; ?></p>
    <p style="text-align: right;"><?php renderShareLinks('test','the foo'); ?></p>
    </div>
    </div><!-- /row -->
    <?php
    bottom();
  }

  public static function edit($id) {
    top();
    $story = getDatabase()->one(" select * from story where id = :id and personid = :personid ",array('id'=>$id,'personid'=>getSession()->get('user_id')));

    if (!$story['id']) {
      print "Story not found, or you are not the author\n";
      bottom();
      return;
    }

    if ($story['deleted']) {
      print "Story is deleted\n";
      bottom();
      return;
    }

    ?>

    <div class="row-fluid">
    <div class="span6">

    <script>
    function preview() {
      html = $('#storybody').val();
      $('#previewbody').html(html);
      return false;
    }
    </script>
		<script src="<?php print OttWatchConfig::WWW; ?>/vendor/ckeditor/ckeditor.js"></script>
		<script src="<?php print OttWatchConfig::WWW; ?>/vendor/ckeditor/adapters/jquery.js"></script>

    <!--
    Created: <?php print $story['created']; ?><br/>
    Updated: <?php print $story['updated']; ?><br/>
    -->
    <form class="form-horizontal" method="post" action="../save">
    <input type="hidden" name="id" value="<?php print $id; ?>"/>

    <p>
    <center>
    <input style="width: 98%;" type="text" name="title" value="<?php print $story['title']; ?>"/>
    </center>
    </p>
    <p>
    <center>
    <button type="submit" name="save" class="btn">Save</button>
    <button type="submit" name="publish" value="1" class="btn">Publish</button>
    <button type="submit" name="unpublish" value="1" class="btn">Unpublish</button>
    <button type="submit" name="delete" value="1" class="btn">Delete</button>
    <button type="submit" onclick="return preview()" name="delete" value="1" class="btn">Preview</button>
    </center>
    </p>
    <textarea id="storybody" name="body" rows="80" style=""><?php print $story['body']; ?></textarea>
    <script>
    $( '#storybody' ).ckeditor({
     toolbar: [ 
	      ['Source','Maximize','Save'],
	      ['Bold','Italic','Underline','Strike','Subscript','Superscript','-','RemoveFormat' ],
        [ 'NumberedList','BulletedList','-','Outdent','Indent','-','Blockquote','CreateDiv','-','JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
        [ 'Image','Link','Unlink','Anchor','HorizontalRule' ],
        [ 'Format','Font','FontSize' ],
      ],
      height: '450'
    });
    </script>
    </form>

    </div>

    <div class="span6">
    <h1 id="previewtitle"><?php print "{$story['title']}\n"; ?></h1>
    <div id="previewbody"><?php print $story['body']; ?></div>
    </div>
    </div><!-- /row -->

    <?php

    bottom();
  }

  public static function save() {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $body = $_POST['body'];

    $publish = $_POST['publish'];
    $unpublish = $_POST['unpublish'];
    $delete = $_POST['delete'];

    if ($delete == '1') {
	    getDatabase()->execute(" 
	      update story set 
          published = 0,
          deleted = 1,
	        updated = CURRENT_TIMESTAMP 
	      where 
	        id = :id 
	        and personid = :personid
	      ",array('id'=>$id,'personid'=>getSession()->get('user_id')));
    }
    if ($unpublish == '1') {
	    getDatabase()->execute(" 
	      update story set 
          published = 0,
	        updated = CURRENT_TIMESTAMP 
	      where 
	        id = :id 
	        and personid = :personid
	      ",array('id'=>$id,'personid'=>getSession()->get('user_id')));
    }

    if ($publish == '1') {
	    getDatabase()->execute(" 
	      update story set 
          published = 1,
	        updated = CURRENT_TIMESTAMP 
	      where 
	        id = :id 
	        and personid = :personid
	      ",array('id'=>$id,'personid'=>getSession()->get('user_id')));
    }

    getDatabase()->execute(" 
      update story set 
        title = :title, 
        body = :body, 
        updated = CURRENT_TIMESTAMP 
      where 
        id = :id 
        and personid = :personid
      ",array('id'=>$id,'title'=>$title,'body'=>$body,'personid'=>getSession()->get('user_id')));

    header("Location: edit/$id");

  }

}

?>
