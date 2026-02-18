<div id="stats" class="vote-container">
  <h5><?= $this->t('.title') ?></h5>
  <ul>
    <li><?= $this->t('.id') ?>: <?= $this->post->id ?></li>
    <li><?= $this->t('.posted') ?>: <?= $this->t(['.posted_data_html', 'time' => $this->linkTo($this->t(['time.x_ago', 't' => $this->timeAgoInWords($this->post->created_at)]), ["#index", 'tags' => "date:" . substr($this->post->created_at, 0, 10)], ['title' => substr(date('r', strtotime($this->post->created_at)), 0, -6)]), 'user' => $this->linkToIf($this->post->user_id, $this->post->author(), ['user#show', 'id' => $this->post->user_id])]) ?></li>
    <?php if (current_user()->is_admin() && $this->post->approver) : ?>
      <li><?= $this->t('.approver') ?>: <?= $this->h($this->post->approver->name) ?></li>
    <?php endif ?>
    <?php if ($this->post->image()) : ?>
      <li><?= $this->t('.size') ?>: <?= $this->post->width ?>x<?= $this->post->height ?></li>
    <?php endif ?>
    <?php if ($this->post->source) : ?>
      <?php
      if (strpos($this->post->source, 'http') === 0) :
        $init = substr($this->post->source, 4, 1) == 's' ? 8 : 7;
      ?>
        <li><?= $this->t('.source') ?>: <?= $this->linkTo($this->h(substr($this->post->source, $init, 20) . "..."), $this->post->normalized_source(), array('rel' => 'nofollow', 'target' => '_blank')) ?></li>
      <?php else: ?>
        <li><?= $this->t('.source') ?>: <?= $this->h($this->post->source) ?></li>
      <?php endif ?>
    <?php endif ?>
    <li><?= $this->t('.rating') ?>: <?= $this->post->pretty_rating() ?> <?= $this->vote_tooltip_widget() ?></li>

    <li>
      <?= $this->t('.score') ?>: <span id="post-score-<?= $this->post->id ?>"><?= $this->post->score ?></span>
      <?= $this->vote_widget(current_user()) ?>
    </li>

    <li><?= $this->t('.favorited_by') ?>: <span id="favorited-by"><?= $this->favorite_list($this->post) ?></span> <span id="favorited-by-more"></span></li>
  </ul>
</div>

<?= $this->contentFor('post_cookie_javascripts', function() { ?>
<script type="text/javascript">
  var widget = new VoteWidget($("stats"));
  widget.set_post_id(<?= $this->post->id ?>);
  widget.init_hotkeys();

  Post.init_add_to_favs(<?= $this->post->id ?>, $("add-to-favs"), $("remove-from-favs"));
</script>
<?php }) ?>

