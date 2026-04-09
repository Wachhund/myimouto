<h3><?= $this->t('pool_add') ?></h3>

<?= $this->linkTo($this->imageTag($this->post->preview_url(), ['loading' => 'lazy']), ['controller' => "post", 'action' => "show", 'id' => $this->post->id]) ?>

<p><?= $this->t('pool_add_text') ?></p>

<?= $this->formTag("#add_post", function(){ ?>
  <?= $this->hiddenFieldTag("post_id", $this->post->id) ?>

  <table>
    <tbody>
      <tr>
        <th width="15%"><label for="pool_name"><?=$this->$this->t('pool_pool') ?></label></th>
        <td width="85%">
          <select name="pool_id">
            <?= $this->options_from_collection_for_select($this->pools, 'id', 'pretty_name') ?>
          </select>
      </tr>
      <tr>
        <th><label for="pool_sequence"><?=$this->t('pool_order') ?></label></th>
        <td><?= $this->textField('pool', 'sequence', ['size' => 5, 'value' => ""]) ?></td>
      </tr>
    </tbody>
  </table>

  <?= $this->submitTag($this->t('pool_add')) ?> <?= $this->buttonToFunction($this->t('pool_cancel'), "history.back()") ?>
<?php }) ?>

<?= $this->partial("footer") ?>
