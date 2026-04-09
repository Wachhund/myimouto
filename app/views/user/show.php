<?php if ($this->user->has_avatar()) : ?>
  <div style="width: 25em; height: <?= max($this->user->avatar_height, 80) ?>px; position: relative;">
    <div style="position: absolute; bottom: 0;">
      <?= $this->avatar($this->user, 1) ?>
    </div>
    <div style="position: absolute; bottom: 0; margin-bottom: 15px; left: <?= $this->user->avatar_width+5 ?>px; ">
      <?php if (current_user()->has_permission($this->user, 'id')) : ?>
        &nbsp;<?= $this->linkTo($this->t('user_edit'), array('user#set_avatar', 'id' => $this->user->avatar_post->id, 'user_id' => $this->user->id)) ?>
        <?= $this->linkTo($this->t('.avatar.remove.link'), array('controller' => 'user', 'action' => 'remove_avatar', 'id' => $this->user->id ), array('method' => 'post', 'confirm' => $this->t('.avatar.remove.confirm'))) ?>
      <?php endif ?>
      <h2><?= $this->h($this->user->pretty_name()) ?></h2>
    </div>
  </div>
<?php else: ?>
  <h2><?= $this->h($this->user->pretty_name()) ?></h2>
<?php endif ?>

<div style="float: left; width: 25em; clear: left;">
  <table width="100%">
    <tr>
      <td width="40%"><strong><?= $this->t('user_join') ?></strong></td>
      <td width="60%"><?= substr($this->user->created_at, 0, 10) ?>
      </td>

    </tr>
    <?php if ($this->user->level < 20 or $this->user->level > 33 or current_user()->is_mod_or_higher()) : ?>
    <tr>
      <td><strong><?= $this->t('user_level') ?></strong></td>
      <td>
        <?= $this->user->pretty_level() ?>
        <?php if ($this->user->is_blocked() && $this->user->ban) : ?>
          <?= $this->t('user_reason2') ?><?= $this->h($this->user->ban->reason) ?><?= $this->t('user_expire2') ?><?= substr($this->user->ban->expires_at, 0, 10) ?><?= $this->t('user_reason3') ?>
        <?php endif ?>
      </td>
    </tr>
    <?php endif ?>
    <tr>
      <td><strong><?= $this->t('user_subs') ?></strong></td>
      <td class="large-text">
        <?= $this->partial("tag_subscription/user_listing", array('user' => $this->user)) ?>
      </td>
    </tr>
    <tr>
      <td><strong><?= $this->t('user_posts') ?></strong></td>
      <td><?= $this->linkTo(Post::where('user_id = ?', $this->user->id)->count(), array('post#index', 'tags' => 'user:'.$this->user->name)) ?></td>
    </tr>
    <tr>
      <td><strong><?= $this->t('user_deleted_posts') ?></strong></td>
      <td><?= $this->linkTo(Post::where('status = "deleted" AND user_id = ?', $this->user->id)->count(), array('post#deleted_index', 'user_id' => $this->user->id)) ?></td>
    </tr>
    <tr>
      <th><?= $this->t('user_votes') ?></th>
      <td>
        <span class="stars">
          <?php foreach(range(1, 3) as $i) : ?>
            <a class="star star-<?= $i ?>" href="<?= $this->urlFor(array('post#index', 'tags' => 'vote:>='.$i.':'.$this->user->name.' order:vote')) ?>">
              <?= $this->user->post_votes->select(array('score' => $i))->size() ?>
              <span class="score-on score-voted score-visible">★</span>
            </a>
          <?php endforeach ?>
        </span>
      </td>
    </tr>
    <tr>
      <td><strong><?= $this->t('user_comments') ?></strong></td>
      <td><?= $this->linkTo(Comment::where(['user_id' => $this->user->id])->count(), array('comment#search', 'query' => 'user:'.$this->user->name)) ?></td>
    </tr>
    <tr>
      <td><strong><?= $this->t('user_edits') ?></strong></td>
      <td><?= $this->linkTo(History::where(['user_id' => $this->user->id])->count(), array('history#index', 'search' => 'user:'.$this->user->name)) ?></td>
    </tr>
    <tr>
      <td><strong><?= $this->t('user_tag_edits') ?></strong></td>
      <td><?= $this->linkTo(History::where(['user_id' => $this->user->id, 'group_by_table' => 'posts'])->count(), array('history#index', 'search' => 'type:post user:'.$this->user->name)) ?></td>
    </tr>
    <tr>
      <td><strong><?= $this->t('user_note_edits') ?></strong></td>
      <td><?= $this->linkTo(NoteVersion::where(['user_id' => $this->user->id])->count(), array('note#history', 'user_id' => $this->user->id)) ?></td>
    </tr>
    <tr>
      <td><strong><?= $this->t('user_wiki_edits') ?></strong></td>
      <td><?= $this->linkTo(WikiPageVersion::where(['user_id' => $this->user->id])->count(), array('wiki#recent_changes', 'user_id' => $this->user->id)) ?></td>
    </tr>
    <tr>
      <td><strong><?= $this->t('user_forum_post') ?></strong></td>
      <td><?= ForumPost::where(['creator_id' => $this->user->id])->count() ?></td>
    </tr>
    <?php if ($this->user->invited_by) : ?>
      <tr>
        <td><strong><?= $this->t('user_invited_by') ?></strong></td>
        <td><?= $this->linkTo($this->h(User::find($this->user->invited_by)->name), array('action' => 'show', 'id' => $this->user->invited_by)) ?></td>
      </tr>
    <?php endif ?>
    <?php if (CONFIG()->starting_level < 30) : ?>
    <tr>
      <td><strong><?= $this->t('user_rec_invites') ?></strong></td>
      <td><?= implode(', ', array_map(function($x){return $this->linkTo($this->h($x->pretty_name()), array('action' => 'show', 'id' => $x->id));}, User::where("invited_by = ?", $this->user->id)->order('id desc')->select('name, id')->limit(5)->take()->members())) ?></td>
    </tr>
    <?php endif ?>
    <?php if (current_user()->is_mod_or_higher()) : ?>
    <tr>
      <td><strong><?= $this->t('user_record') ?></strong></td>
      <td>
        <?php if (!UserRecord::where("user_id = ? AND is_deleted = 0", $this->user->id)->exists()) : ?>
          <?= $this->t('user_record_none') ?>
        <?php else: ?>
          <?= UserRecord::where("user_id = ? AND category = 'positive' AND is_deleted = 0", $this->user->id)->count() - UserRecord::where("user_id = ? AND category = 'negative' AND is_deleted = 0", $this->user->id)->count() ?>
        <?php endif ?>
        (<?= $this->linkTo($this->t('user_record_add'), array('user_record#index', 'user_id' => $this->user->id)) ?>)
      </td>
    </tr>
    <?php endif ?>
    <?php if (current_user()->is_mod_or_higher()) : ?>
      <tr>
        <td><strong><?= $this->t('user_ip') ?></strong></td>
        <td>
          <?php foreach(array_slice($this->user_ips, 0, 5) as $ip) : ?>
          <?= $ip ?>
          <?php endforeach ?>
          <?php if (count($this->user_ips) > 5) : ?>(more)<?php endif ?>
        </td>
      </tr>
    <?php endif ?>
  </table>
</div>

<div style="float: left; width: 60em;">
  <table width="100%">
    <?php foreach ($this->tag_types as $name => $value) : ?> 
    <tr>
      <th>Favorite <?= $name . 's' ?></th>
      <td><?= implode(', ', array_map(function($tag) { return $this->linkTo($this->h(str_replace('_', ' ', $tag["tag"])), array('post#index', 'tags' => "vote:3:{$this->user->name} {$tag['tag']} order:vote"));}, $this->user->voted_tags(array('type' => $value))))?></td>
    </tr>
    <?php endforeach ?> 
    <tr>
      <th>Uploaded Tags</th>
      <td><?= implode(', ', array_map(function($tag) { return $this->linkTo($this->h(str_replace('_', ' ', $tag["tag"])), array('post#index', 'tags' => "user:{$this->user->name} {$tag['tag']}"));}, $this->user->uploaded_tags())) ?></td>
    </tr>
    <?php foreach ($this->tag_types as $name => $value) : ?> 
      <tr>
        <th>Uploaded <?= $name . 's' ?></th>
        <td><?= implode(', ', array_map(function($tag) { return $this->linkTo($this->h(str_replace('_', ' ', $tag["tag"])), array('post#index', 'tags' => "user:{$this->user->name} {$tag['tag']}"));}, $this->user->uploaded_tags(array('type' => $value)))) ?></td>
      </tr>
    <?php endforeach ?> 
  </table>
</div>

<?php foreach($this->user->tag_subscriptions->select(function($x) { return (bool)$x->is_visible_on_profile; }) as $tag_subscription) : ?>
  <div style="margin-bottom: 1em; float: left; clear: both;">
    <h4><?= $this->t('user_sub2') ?><?= $this->h($tag_subscription->name) ?> <?= $this->linkTo("»", ['post#index', 'tags' => 'sub:' . $this->user->name . ':' . $tag_subscription->name]) ?></h4>
    <?= $this->partial("post/posts", array('posts' => $this->user->tag_subscription_posts(5, $tag_subscription->name)->select(function($x) { return CONFIG()->can_see_post(current_user(), $x); }))) ?>
  </div>
<?php endforeach ?>

<div style="margin-bottom: 1em; float: left; clear: both;">
  <h4><?= $this->linkTo($this->t('user_fav3'), array('post#index', 'tags' => 'vote:3:'.$this->user->name.' order:vote')) ?></h4>
  <?= $this->partial("post/posts", array('posts' => !$this->user->recent_favorite_posts()->blank() ? $this->user->recent_favorite_posts()->select(function($x){if(CONFIG()->can_see_post(current_user(), $x))return $x;}) : $this->user->recent_favorite_posts())) ?>
</div>

<div style="margin-bottom: 1em;  float: left; clear: both;">
  <h4><?= $this->linkTo($this->t('user_uploads'), array('post#index', 'tags' => 'user:'.$this->user->name)) ?></h4>
  <?= $this->partial("post/posts", array('posts' => $this->user->recent_uploaded_posts()->select(function($x){if (CONFIG()->can_see_post(current_user(), $x)) return $x;}))) ?>
</div>

<?= $this->contentFor('footer', function() { ?>
  <li><?= $this->linkTo($this->t('user_list'), 'user#index') ?></li>
  <?php if (current_user()->is_mod_or_higher()) : ?>
    <li><?= $this->linkTo($this->t('user_ban'), array('user#block', 'id' => $this->user->id)) ?></li>
  <?php endif ?>
  <?php if (current_user()->is_janitor_or_higher()) : ?>
    <li><?= $this->linkTo($this->t('user_invite_link'), array('user#invites', 'name' => $this->user->name)) ?></li>
  <?php endif ?>
  <li><?= $this->linkTo($this->t('user_send_msg'), array('dmail#compose', 'to' => $this->user->name)) ?></li>
<?php }) ?>

<?= $this->partial("footer") ?>
