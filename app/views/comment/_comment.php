<div class="comment avatar-container" id="c<?= $this->comment->id ?>">
  <div class="author">
    <?php if ($this->comment->user_id) : ?>
      <h6><?= $this->linkTo($this->h($this->comment->pretty_author2()), array('controller' => 'user', 'action' => 'show', 'id' => $this->comment->user_id)) ?></h6>
    <?php else: ?>
      <h6><?= $this->h($this->comment->pretty_author2()) ?></h6>
    <?php endif ?>
    <span class="date" title="Posted at <?= date('r', strtotime($this->comment->created_at)) ?>">
      <?= $this->linkTo($this->t(array('time.x_ago', 't' => $this->timeAgoInWords($this->comment->created_at))), array('post#show', 'id' => $this->comment->post_id, 'anchor' => "c".$this->comment->id)) ?>
    </span>
    <?php if ($this->comment->user and $this->comment->user->has_avatar()) : ?>
      <div class="comment-avatar-container"> <?= $this->avatar($this->comment->user, $this->comment->id) ?> </div>
    <?php endif ?>
  </div>
  <div class="content">
    <div class="body">
      <?= $this->format_inlines($this->format_text($this->comment->body, array('mode' => 'comment')), $this->comment->id) // TODO: Rails.cache.fetch(array( 'type' => '.formatted_body', 'id' => 'comment'.id }) { format_inlines(format_text($this->comment->body, 'mode' => 'comment'), $this->comment->id) ) ?>
    </div>
    <div class="post-footer" style="clear: left;">
      <ul class="flat-list pipe-list">
        <li> <?= $this->linkToFunction($this->t('.quote'), "Comment.quote(".$this->comment->id.")") ?>
        <?php if (current_user()->has_permission($this->comment)) : ?>
          <li> <?= $this->linkTo($this->t('.edit'), array('comment#edit', 'id' => $this->comment->id)) ?>
          <li> <?= $this->linkToFunction($this->t('.delete'), "Comment.destroy(".$this->comment->id.")") ?>
        <?php else: ?>
          <li> <?= $this->linkToFunction($this->t('.flag'), "Comment.flag(".$this->comment->id.")") ?>
        <?php endif ?>
      </ul>
    </div>
  </div>
</div>

