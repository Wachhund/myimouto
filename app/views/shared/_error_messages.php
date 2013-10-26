<?php if (!$this->object->errors()->blank()) : ?>
  <div id="error_explanation">
    There were problems with the following fields:
    <ul>
      <?php foreach ($this->object->errors()->full_messages() as $msg) : ?>
        <li><?= $msg ?></li>
      <?php endforeach ?>
    </ul>
  </div>
<?php endif ?>
