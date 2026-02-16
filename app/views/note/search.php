<h4><?= $this->t('.title') ?></h4>

<?= $this->formTag(['action' => 'search'], ['method' => 'get'], function(){ ?>
  <?= $this->textFieldTag("query", $this->h($this->params()->query),  ['size' => '40']) ?> <?= $this->submitTag($this->t('.search')) ?>
<?php }) ?>

<?php if ($this->notes) : ?>
  <div style="margin-top: 2em;">
    <?php foreach ($this->notes as $note) : ?>
      <div style="float: left; clear: both; margin-bottom: 2em;">
        <div style="float: left; width: 200px;">
          <?= $this->linkTo($this->imageTag($note->post->preview_url(), ['width' => $note->post->preview_dimensions()[0], 'height' => $note->post->preview_dimensions()[1]]), ['post#show', 'id' => $note->post_id]) ?>
        </div>
        <div style="float: left;">
          <?= $this->h($note->formatted_body()) //sanitize ?>
        </div>
      </div>
    <?php endforeach ?>
  </div>

  <div id="paginator">
    <?= $this->willPaginate($this->notes) ?>
  </div>
<?php endif ?>

<?= $this->partial("footer") ?>
