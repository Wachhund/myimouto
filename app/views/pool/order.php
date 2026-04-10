<h3><?=$this->t(['.title_html', 'name' => $this->linkTo($this->h($this->pool->pretty_name()), ['action' => 'show', 'id' => $this->pool->id])]) ?></h3>
<p><?=$this->t('.info') ?></p>

<script type="text/javascript">
  function orderAutoFill() {
    var i = 0
    var step = parseInt(prompt('<?=$this->t('.interval') ?>'))

    $$(".pp").each(function(x) {
      x.value = i
      i += step
    })
  }

  function orderReverse() {
    var orders = []
    $$(".pp").each(function(x) {
      orders.push(x.value)
    })
    var i = orders.size() - 1
    $$(".pp").each(function(x) {
      x.value = orders[i]
      i -= 1
    })
  }

  function orderShift(start, offset) {
    var found = false;
    $$(".pp").each(function(x) {
      if(x.id == "pool_post_sequence_" + start)
        found = true;
      if(!found)
        return;
      x.value = Number(x.value) + offset;
    });
  }
</script>

<?= $this->formTag(function () { ?>
  <?= $this->hiddenFieldTag("id", $this->pool->id) ?>
  <table>
    <thead>
      <tr>
        <th></th>
        <th><?=$this->t('.order') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->pool_posts as $pp) : ?>
        <tr>
          <td>
            <?php if ($pp->post->can_be_seen_by($this->current_user)) : ?>
              <?= $this->linkTo($this->imageTag($pp->post->preview_url(), ['width' => $pp->post->preview_dimensions()[0], 'height' => $pp->post->preview_dimensions()[1], 'loading' => 'lazy']), ["post#show", 'id' => $pp->post_id], ['title' => $pp->post->tags()]) ?>
            <?php endif ?>
          </td>
          <td>
            <?= $this->textFieldTag("pool_post_sequence[{$pp->id}]", $pp->sequence, ['class' => "pp", 'size' => 5, 'tabindex' => 1]) ?>
            <?= $this->linkToFunction($this->t('.plus_one'), "orderShift({$pp->id}, +1)", ['class' => "text-button"]) ?>
            <?= $this->linkToFunction($this->t('.minus_one'), "orderShift({$pp->id}, -1)", ['class' => "text-button"]) ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('buttons.save'), ['tabindex' => 2]) ?> <?= $this->buttonToFunction($this->t('.auto_order'), "orderAutoFill()", ['tabindex' => 2]) ?> <?= $this->buttonToFunction($this->t('.reverse'), "orderReverse()", ['tabindex' => 2]) ?> <?= $this->buttonToFunction($this->t('buttons.cancel'), "history.back()", ['tabindex' => 2]) ?></td>
      </tr>
    </tfoot>
  </table>
<?php }) ?>
