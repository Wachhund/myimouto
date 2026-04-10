<style>
.flag-cat-duplicate { color: #2563eb; }
.flag-cat-inferior { color: #ea580c; }
.flag-cat-rule_violation { color: #dc2626; }
.flag-cat-dnp_artist { color: #9333ea; }
.flag-cat-other { color: #6b7280; }
.flag-cat-label { font-weight: bold; margin-right: 0.4em; }
.flag-resolve-btn { font-size: 0.85em; padding: 0.15em 0.6em; cursor: pointer; margin-left: 0.5em; }
.flag-resolved-label { font-size: 0.85em; color: #22c55e; margin-left: 0.5em; }
</style>

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

  function resolve_flag(flag_id, btn) {
    btn.disabled = true;
    new Ajax.Request("/post/resolve_flag.json/" + flag_id, {
      method: "post",
      onSuccess: function() {
        var label = document.createElement("span");
        label.className = "flag-resolved-label";
        label.textContent = "Resolved";
        btn.parentNode.replaceChild(label, btn);
      },
      onFailure: function(req) {
        btn.disabled = false;
        var resp = req.responseJSON;
        notice("Error: " + (resp && resp.reason ? resp.reason : "Failed to resolve flag"));
      }
    });
  }
</script>

<div style="margin-bottom: 2em;">
  <h2><?= $this->t('.pending') ?></h2>
  <form method="post" action="/post/moderate">
    <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token, ['id' => '']) ?>
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
            <td><?= $this->linkTo($this->imageTag($p->preview_url(), ['width' => $p->preview_dimensions()[0], 'height' => $p->preview_dimensions()[1], 'loading' => 'lazy']), ['post#show', 'id' => $p->id]) ?></td>
            <td class="checkbox-cell">
              <ul>
                <li><?= $this->t(['.uploaded_by_when_html', 'u' => $this->linkTo($p->author(), ['user#show', 'id' => $p->user->id]), 't_ago' => $this->t(['time.x_ago', 't' => $this->timeAgoInWords($p->created_at)]), 'mod' => $this->linkTo($this->t('.mod'), ['#moderate', 'query' => 'user:'.$p->author()])]) ?></li>
                <li><?= $this->t('.rating') ?>: <?= $p->pretty_rating() ?></li>
                <?php if ($p->parent_id) : ?>
                  <li><?= $this->t('.parent') ?>: <?= $this->linkTo($p->parent_id, ['action' => 'moderate', 'query' => 'parent:'.$p->parent_id]) ?></li>
                <?php endif ?>
                <li><?= $this->t('.tags') ?>: <?= $this->h($p->cached_tags) ?></li>
                <li><?= $this->t('.score') ?>: <span id="post-score-<?= $p->id ?>"><?= $p->score ?></span></li>
                <?php $p_flag = $p->latest_flag(); if ($p_flag) : ?>
                <li>
                  <?php if ($p_flag->reason_category) : ?>
                    <span class="flag-cat-label flag-cat-<?= $this->h($p_flag->reason_category) ?>">[<?= $this->h($p_flag->reason_category) ?>]</span>
                  <?php endif ?>
                  <?= $this->t('.reason') ?>: <?= $this->h($p_flag->reason) ?> (<?php if (!$p_flag->user_id): ?>automatic flag<?php else: ?><?= $this->linkTo($this->h($p_flag->author()), ['user#show', 'id' => $p_flag->user_id]) ?><?php endif ?>)
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
    <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token, ['id' => '']) ?>
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
            <td><?= $this->linkTo($this->imageTag($p->preview_url(), ['width' => $p->preview_dimensions()[0], 'height' => $p->preview_dimensions()[1], 'loading' => 'lazy']), ['post#show', 'id' => $p->id]) ?></td>
            <td class="checkbox-cell">
              <ul>
                <li><?= $this->t(['.uploaded_by_when_html', 'u' => $this->linkTo($p->author(), ['user#show', 'id' => $p->user->id]), 't_ago' => $this->t(['time.x_ago', 't' => $this->timeAgoInWords($p->created_at)]), 'mod' => $this->linkTo($this->t('.mod'), ['#moderate', 'query' => 'user:'.$p->author()])]) ?></li>
                <li><?= $this->t('.rating') ?>: <?= $p->pretty_rating() ?></li>
                <?php if ($p->parent_id) : ?>
                  <li><?= $this->t('.parent') ?>: <?= $this->linkTo($p->parent_id, ['action' => 'moderate', 'query' => 'parent:'.$p->parent_id]) ?></li>
                <?php endif ?>
                <li><?= $this->t('.tags') ?>: <?= $this->h($p->cached_tags) ?></li>
                <li><?= $this->t('.score') ?>: <?= $p->score ?> (vote <?= $this->linkToFunction($this->t('.down'), "Post.vote(-1, {$p->id}, {})") ?>)</li>
                <?php
                  $post_flags = isset($this->flags_by_post[$p->id]) ? $this->flags_by_post[$p->id] : [];
                  if (!empty($post_flags)) :
                    foreach ($post_flags as $pf) :
                ?>
                <li>
                  <?php if ($pf->reason_category) : ?>
                    <span class="flag-cat-label flag-cat-<?= $this->h($pf->reason_category) ?>">[<?= $this->h($pf->reason_category) ?>]</span>
                  <?php endif ?>
                  <?= $this->t('.reason') ?>: <?= $this->h($pf->reason) ?>
                  (<?php if (!$pf->user_id): ?>automatic flag<?php else: ?><?= $this->linkTo($this->h($pf->author()), ['user#show', 'id' => $pf->user_id]) ?><?php endif ?>)
                  <button type="button" class="flag-resolve-btn" onclick="resolve_flag(<?= $pf->id ?>, this)">Resolve</button>
                </li>
                <?php
                    endforeach;
                  else :
                    $p_flag = $p->latest_flag();
                    if ($p_flag) :
                ?>
                <li>
                  <?php if ($p_flag->reason_category) : ?>
                    <span class="flag-cat-label flag-cat-<?= $this->h($p_flag->reason_category) ?>">[<?= $this->h($p_flag->reason_category) ?>]</span>
                  <?php endif ?>
                  <?= $this->t('.reason') ?>: <?= $this->h($p_flag->reason) ?>
                  (<?php if (!$p_flag->user_id): ?>automatic flag<?php else: ?><?= $this->linkTo($this->h($p_flag->author()), ['user#show', 'id' => $p_flag->user_id]) ?><?php endif ?>)
                </li>
                <?php
                    endif;
                  endif;
                ?>
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
