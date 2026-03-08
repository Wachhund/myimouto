<?php $this->provide('title', $this->t('.title')) ?>
<script type="text/javascript">
  function highlight_row(checkbox) {
    var row = checkbox.parentNode::parentNode
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

<form method="post" action="/comment/moderate">
  <?= $this->hiddenFieldTag('csrf_token', $this->csrf_token, ['id' => '']) ?>
  <table>
    <thead>
      <tr>
        <th></th>
        <th><?= $this->t('.author') ?></th>
        <th><?= $this->t('.body') ?></th>
      </tr>
    </thead>
    <tfoot>
      <tr>
        <td colspan="3">
          <?= $this->buttonToFunction($this->t('.select_all'), "$$('.c').each(function (i) {i.checked = true; highlight_row(i)}); return false") ?>
          <?= $this->buttonToFunction($this->t('.select_invert'), "$$('.c').each(function (i) {i.checked = !i.checked; highlight_row(i)}); return false") ?>
          <?= $this->submitTag($this->t('.approve')) ?>
          <?= $this->submitTag($this->t('.delete')) ?>
        </td>
      </tr>
    </tfoot>
    <tbody>
      <?php foreach ($this->comments as $c) : ?>
        <tr>
          <td><input type="checkbox" class="c" name="c[<?= $c->id ?>]" onclick="highlight_row(this)"></td>
          <td><?= $this->linkTo($this->h($c->author()), ["post#show", 'id' => $c->post_id]) ?></td>
          <td><?= $this->h($c->body) ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</form>
