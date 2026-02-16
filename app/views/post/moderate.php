<form method="get" action="/post/moderate">
  <?= $this->textFieldTag("query", $this->h($this->params()->query), ['size' => '40']) ?>
  <?= $this->submitTag($this->t('buttons.search')) ?>
</form>

<script type="text/javascript">
  function highlight_row(checkbox) {
    var row = checkbox.parentNode.parentNode
    if (row.original_class == null) {
      row.original_class = row.className
    }

    if (checkbox.checked) {
      row.className = "highlight"
    } else {
      row.className = row.original_class
    }
  }
</script>

<div style="margin-bottom: 2em;">
  <h2><?= $this->t('.pending') ?></h2>
  <form method="post" action="/post/moderate">
    <?= $this->hiddenFieldTag("reason", "") ?>

    <table width="100%">
      <tfoot>
        <tr>
          <td colspan="3">
            <?= $this->buttonToFunction($this->t('buttons.select.all'), "$$('.p').each(function (i) {i.checked = true; highlight_row(i)})") ?>
            <?= $this->buttonToFunction($this->t('buttons.select.invert'), "$$('.p').each(function (i) {i.checked = !i.checked; highlight_row(i)})") ?>
            <?= $this->submitTag($this->t('buttons.approve')) ?>
            <?= $this->submitTag($this->t('buttons.delete'), ['onclick' => "var reason = prompt('".$this->t('.prompt_reason')."'); if (reason != null) {\$('reason').value = reason; return true} else {return false}"]) ?>
          </td>
        </tr>
      </tfoot>
      <tbody>
        <?php foreach ($this->pending_posts as $p) : ?>
          <tr class="<?php if ($p->score > 2): ?>good<?php elseif ($p->score < -2): ?>bad<?php endif ?> <?= $this->cycle('even', 'odd') ?>">
            <td><input type="checkbox" class="p" name="ids[<?= $p->id ?>]" onclick="highlight_row(this)"></td>
            <td><?= $this->linkTo($this->imageTag($p->preview_url(), ['width' => $p->preview_dimensions()[0], 'height' => $p->preview_dimensions()[1]]), ['post#show', 'id' => $p->id]) ?></td>
            <td class="checkbox-cell">
              <ul>
                <li><?= $this->t(['.uploaded_by_when_html', 'u' => $this->linkTo($p->author(), ['user#show', 'id' => $p->user->id]), 't_ago' => $this->t(['time.x_ago', 't' => $this->timeAgoInWords($p->created_at)]), 'mod' => $this->linkTo($this->t('.mod'), ['#moderate', 'query' => 'user:'.$p->author()])]) ?></li>
                <li><?= $this->t('.rating') ?>: <?= $p->pretty_rating() ?></li>
                <?php if ($p->parent_id) : ?>
                  <li><?= $this->t('.parent') ?>: <?= $this->linkTo($p->parent_id, ['action' => 'moderate', 'query' => 'parent:'.$p->parent_id]) ?></li>
                <?php endif ?>
                <li><?= $this->t('.tags') ?>: <?= $this->h($p->cached_tags) ?></li>
                <li><?= $this->t('.score') ?>: <span id="post-score-<?= $p->id ?>"><?= $p->score ?></span></li>
                <?php if ($p->flag_detail) : ?>
                <li>
                  <?= $this->t('.reason') ?>: <?= $this->h($p->flag_detail->reason) ?> (<?php if (!$p->flag_detail->user_id): ?>automatic flag<?php else: ?><?= $this->linkTo($this->h($p->flag_detail->author()), ['user#show', 'id' => $p->flag_detail->user_id]) ?><?php endif ?>)
                </li>
                <?php endif ?>
                <li><?= $this->t('.size') ?>: <?= $this->numberToHumanSize($p->file_size) ?>, <?= $p->width ?>x<?= $p->height ?></li>
              </ul>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </form>
</div>

<div>
  <h2><?= $this->t('.flagged') ?></h2>
  <form method="post" action="/post/moderate">
    <?= $this->hiddenFieldTag("reason2", "") ?>

    <table width="100%">
      <tfoot>
        <tr>
          <td colspan="3">
            <?= $this->buttonToFunction($this->t('buttons.select.all'), "$$('.f').each(function (i) {i.checked = true; highlight_row(i)})") ?>
            <?= $this->buttonToFunction($this->t('buttons.select.invert'), "$$('.f').each(function (i) {i.checked = !i.checked; highlight_row(i)})") ?>
            <?= $this->submitTag($this->t('buttons.approve')) ?>
            <?= $this->submitTag($this->t('buttons.delete'), ['onclick' => "var reason = prompt('".$this->t('.prompt_reason')."'); if (reason != null) {\$('reason2').value = reason; return true} else {return false}"]) ?>
          </td>
        </tr>
      </tfoot>
      <tbody>
        <?php foreach ($this->flagged_posts as $p) : ?>
          <tr class="<?= $this->cycle('even', 'odd') ?>">
            <td><input type="checkbox" class="f" name="ids[<?= $p->id ?>]" onclick="highlight_row(this)"></td>
            <td><?= $this->linkTo($this->imageTag($p->preview_url(), ['width' => $p->preview_dimensions()[0], 'height' => $p->preview_dimensions()[1]]), ['post#show', 'id' => $p->id]) ?></td>
            <td class="checkbox-cell">
              <ul>
                <li><?= $this->t(['.uploaded_by_when_html', 'u' => $this->linkTo($p->author(), ['user#show', 'id' => $p->user->id]), 't_ago' => $this->t(['time.x_ago', 't' => $this->timeAgoInWords($p->created_at)]), 'mod' => $this->linkTo($this->t('.mod'), ['#moderate', 'query' => 'user:'.$p->author()])]) ?></li>
                <li><?= $this->t('.rating') ?>: <?= $p->pretty_rating() ?></li>
                <?php if ($p->parent_id) : ?>
                  <li><?= $this->t('.parent') ?>: <?= $this->linkTo($p->parent_id, ['action' => 'moderate', 'query' => 'parent:'.$p->parent_id]) ?></li>
                <?php endif ?>
                <li><?= $this->t('.tags') ?>: <?= $this->h($p->cached_tags) ?></li>
                <li><?= $this->t('.score') ?>: <?= $p->score ?> (vote <?= $this->linkToFunction($this->t('.down'), "Post.vote(-1, {$p->id}, {})") ?>)</li>
                <?php if ($p->flag_detail) : ?>
                <li>
                  <?= $this->t('.reason') ?>: <?= $this->h($p->flag_detail->reason) ?> (<?php if (!$p->flag_detail->user_id): ?>automatic flag<?php else: ?><?= $this->linkTo($this->h($p->flag_detail->author()), ['user#show', 'id' => $p->flag_detail->user_id]) ?><?php endif ?>)
                </li>
                <?php endif ?>
                <li><?= $this->t('.size') ?>: <?= $this->numberToHumanSize($p->file_size) ?>, <?= $p->width ?>x<?= $p->height ?></li>
              </ul>
            </td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </form>

  <script type="text/javascript">
    var cells = $$(".checkbox-cell")
    $$(".checkbox-cell").invoke("observe", "click", function(e) {this.up().firstDescendant().down("input").click()})
    <?php $this->pending_posts->merge($this->flagged_posts)->unique()->each(function($post){ ?>
      Post.register(<?= $post->toJson() ?>)
    <?php }) ?>
  </script>
</div>

<?= $this->partial('footer') ?>
