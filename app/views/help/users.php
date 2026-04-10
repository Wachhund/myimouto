<div class="help">
  <h1>Help: Accounts</h1>

  <div class="section">
    <p>There are three types of accounts: basic, privileged, and blocked.</p>
    <p>See the <?= $this->linkTo("signup", "user#signup") ?> page for more details.</p>
  </div>
</div>

<?php $this->contentFor("subnavbar", function () { ?>
  <li><?= $this->linkTo("Help", "#index") ?></li>
<?php }) ?>