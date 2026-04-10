<div class="help">
  <h1>Help: Comments</h1>

  <div class="section">
    <p>All comments are formatted using <?= $this->linkTo("DText", "#dtext") ?>.</p>
  </div>
</div>

<?php $this->contentFor("subnavbar", function () { ?>
  <li><?= $this->linkTo("Help", "#index") ?></li>
<?php }) ?>