
<?php if (!$this->from) : ?>
  <?= $this->formTag([], ['method' => "get"], function () { ?>
  <?= $this->hiddenFieldTag('to', $this->to->id) ?>
  <?= $this->t('.from.from_number') ?>
  <?= $this->textFieldTag('from', "", ['class' => "fp", 'size' => 5, 'tabindex' => 1]) ?>
  <br>
  <?= $this->submitTag($this->t('.from.transfer'), ['tabindex' => 2]) ?>
  <?= $this->buttonToFunction($this->t('buttons.cancel'), "history.back()", ['tabindex' => 2]) ?>
  <script type="text/javascript">$("from").focus()</script>
<?php }) ?>

<?php else: ?>
<h3>
  <?=
    $this->t('.title_html', [
        'from' => $this->linkTo($this->h($this->from->pretty_name()), ['#show', 'id' => $this->from->id]),
        'to' => $this->linkTo($this->h($this->to->pretty_name()), ['#show', 'id' => $this->to->id]),
    ])
    ?>
</h3>

<div>
  <?= $this->t('.info_what') ?>
</div>
<?php if ($this->truncated) : ?>
<div>
 <?= $this->t('.info_truncated') ?>
 <b><?= $this->t('.info_truncated_warning') ?></b>
</div>
<?php endif ?>

<div><?= $this->t('.reverse_text_html', ['reverse' => $this->linkTo($this->t('.reverse'), ['action' => "transfer_metadata", 'from' => $this->to->id, 'to' => $this->from->id])]) ?></div>

<?= $this->formTag("post#update_batch", function () { ?>
<?= $this->hiddenFieldTag("url", ($this->urlFor(["pool#show", 'id' => $this->to->id]))) ?>
  <table>
    <thead>
      <tr>
        <th><?= $this->t('.table.from') ?></th>
        <th><?= $this->t('.table.to') ?></th>
        <th><?= $this->t('.table.tags') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($this->posts as $pp) : ?>
        <?php $fp = $pp['from'];
          $tp = $pp['to'] ?>
        <tr>
          <td>
            <?php if ($fp->can_be_seen_by($this->current_user)) : ?>
              <?= $this->linkTo($this->imageTag($fp->preview_url(), ['width' => $fp->preview_dimensions()[0], 'height' => $fp->preview_dimensions()[1], 'loading' => 'lazy']), ["post#show", 'id' => $fp->id], ['title' => $fp->cached_tags])?>
            <?php endif ?>
          </td>
          <td>
            <?php if ($tp->can_be_seen_by($this->current_user)) : ?>
              <?= $this->linkTo($this->imageTag($tp->preview_url(), ['width' => $tp->preview_dimensions()[0], 'height' => $tp->preview_dimensions()[1], 'loading' => 'lazy']), ["post#show", 'id' => $tp->id], ['title' => $tp->cached_tags])?>
            <?php endif ?>
          </td>
          <td>
            <?= $this->hiddenFieldTag('post[' . $tp->id . '][old_tags]', $tp->cached_tags) ?>
            <?= $this->textFieldTag('post[' . $tp->id . '][tags]', $pp['tags'], ['class' => "fp", 'size' => 45, 'tabindex' => 1]) ?>
          </td>
        </tr>
      <?php endforeach ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2"><?= $this->submitTag($this->t('.table.transfer'), ['tabindex' => 2]) ?> <?= $this->buttonToFunction($this->t('buttons.cancel'), "history.back()", ['tabindex' => 2]) ?></td>
      </tr>
    </tfoot>
  </table>
<?php }) ?>
<?php endif ?>
