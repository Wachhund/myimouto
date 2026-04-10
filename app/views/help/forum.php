<div class="help">
  <h1>Help: Forum</h1>

  <div class="section">
    <p>All forum posts are formatted using <?= $this->linkTo("DText", "#dtext") ?>.</p>
  </div>
</div>

<?php $this->contentFor("subnavbar", function () { ?>
  <li><?= $this->linkTo("Help", "#index") ?></li>
<?php }) ?>
