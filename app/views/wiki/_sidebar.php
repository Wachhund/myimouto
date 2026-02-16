<div class="sidebar">
  <div style="margin-bottom: 1em;">
    <h6>Search</h6>
    <?= $this->formTag(['action' => "index"], ['method' => "get"], function(){ ?>
      <?= $this->textFieldTag("query", $this->h($this->params()->query), ['size' => 20, 'id' => "search-box"]) ?>
    <?php }) ?>
  </div>

  <?= $this->partial("recently_revised") ?>
</div>
