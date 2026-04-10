<div class="comment avatar-container">
  <div class="author">
    <h6 class="author"><?= $this->linkTo($this->h($this->post->author()), ['controller' => "user", 'action' => "show", 'id' => $this->post->creator_id]) ?></h6>
    <span class="date"><?= $this->linkTo($this->t(['time.x_ago', 't' => $this->timeAgoInWords($this->post->created_at)]), ['action' => "show", 'id' => $this->post->id]) ?></span>
    <?php if ($this->post->creator->has_avatar()) : ?>
      <div class="forum-avatar-container"> <?= $this->avatar($this->post->creator, $this->post->id) ?> </div>
    <?php endif ?>
  </div>
  <div class="content">
    <?php if ($this->post->is_parent()) : ?>
      <h6><?= $this->h($this->post->title) ?></h6>
    <?php else: ?>
      <h6 class="response-title"><?= $this->h($this->post->title) ?></h6>
    <?php endif ?>
    <div class="body">
      <?= $this->format_inlines($this->format_text($this->post->body), $this->post->id) ?>
    </div>
    <?php if (empty($this->preview)) : ?>
    <div class="post-footer" style="clear: left;">
      <?php
        # Use pre-loaded data when available (N+1 optimization), fall back to per-post queries
        if (isset($this->vote_scores)) {
            $forum_vote_score = $this->vote_scores[$this->post->id] ?? 0;
        } else {
            $forum_vote_score = ForumPostVote::post_score($this->post->id);
        }
        if (!current_user()->is_anonymous()) {
            if (isset($this->user_votes)) {
                $forum_user_vote = $this->user_votes[$this->post->id] ?? null;
            } else {
                $forum_user_vote = ForumPostVote::user_vote(current_user()->id, $this->post->id);
            }
        } else {
            $forum_user_vote = null;
        }
      ?>
      <span class="forum-post-votes" data-post-id="<?= $this->post->id ?>" style="margin-right: 0.5em;">
        <?php if (!current_user()->is_anonymous()) : ?>
          <a href="#" class="forum-vote-btn<?= $forum_user_vote === 1 ? ' forum-vote-active' : '' ?>" data-vote-score="1" data-post-id="<?= $this->post->id ?>" title="Vote up">+1</a>
        <?php endif ?>
        <span class="forum-vote-score" title="Score"><?= $forum_vote_score ?></span>
        <?php if (!current_user()->is_anonymous()) : ?>
          <a href="#" class="forum-vote-btn<?= $forum_user_vote === -1 ? ' forum-vote-active' : '' ?>" data-vote-score="-1" data-post-id="<?= $this->post->id ?>" title="Vote down">-1</a>
        <?php endif ?>
        <?php if (!current_user()->is_anonymous() && $forum_user_vote !== null) : ?>
          <a href="#" class="forum-vote-remove" data-post-id="<?= $this->post->id ?>" title="Remove vote">&#x2715;</a>
        <?php endif ?>
      </span>
      <ul class="flat-list pipe-list">
      <?php if (current_user()->has_permission($this->post, 'creator_id')) : ?>
        <li> <?= $this->linkTo($this->t('.edit'), ['action' => "edit", 'id' => $this->post->id, 'page' => (int)$this->params()->page]) ?>
        <li> <?= $this->linkTo($this->t('.delete'), ["#destroy", 'id' => $this->post->id], ['confirm' => $this->t('.delete_confirm'), 'method' => 'post']) ?>
      <?php endif ?>
      <?php if ($this->post->is_parent() && current_user()->is_mod_or_higher()) : ?>
        <?php if ($this->post->is_sticky) : ?>
          <li> <?= $this->linkTo($this->t('.unstick'), ['action' => "unstick", 'id' => $this->post->id], ['method' => 'post']) ?>
        <?php else: ?>
          <li> <?= $this->linkTo($this->t('.stick'), ['action' => "stick", 'id' => $this->post->id], ['method' => 'post']) ?>
        <?php endif ?>
        <?php if ($this->post->is_locked) : ?>
          <li> <?= $this->linkTo($this->t('.unlock'), ['action' => "unlock", 'id' => $this->post->id], ['method' => 'post']) ?>
        <?php else: ?>
          <li> <?= $this->linkTo($this->t('.lock'), ['action' => "lock", 'id' => $this->post->id], ['method' => 'post']) ?>
        <?php endif ?>
      <?php endif ?>
      <li> <?= $this->linkToFunction($this->t('.quote'), "Forum.quote({$this->post->id})") ?>
      </ul>
    </div>
    <?php endif ?>
  </div>
</div>
