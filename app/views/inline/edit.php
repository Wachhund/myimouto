<?php
if ($this->inline->inline_images->size() > 0) {
    list($block, $script, $inline_html_id) = $this->format_inline($this->inline, 0, "inline", "");
} else {
    $block = $script = $inline_html_id = null;
}
?>

<div>
  <?= $this->t('.tag', ['id' => $this->inline->id]) ?>
  <?php if (current_user()->has_permission($this->inline)) : ?>

    | <a href="#" onclick='InlineImage.expand("<?= $inline_html_id ?>"); return false;'><?= $this->t('.preview') ?></a>

    <?php if ($this->inline->inline_images->size() < 9) : ?>
      <span id="post-add-button">| <a href="#" onclick="show_post_add(); return false;"><?= $this->t('.add_image') ?></a></span>
    <?php endif ?>
  <?php endif ?>
  <p>


  <div id="post-add" style="display: none;">
    <?= $this->formTag(['action' => "add_image"], ['level' => 'member', 'multipart' => true], function () { ?>
      <?= $this->hiddenFieldTag("id", $this->inline->id) ?>
      <div id="posts">
        <table class="form">
          <tbody>
            <tr>
              <th width="15%"><label for="image_file"><?= $this->t('.file') ?></label></th>
              <td width="85%"><?= $this->fileField("image", "file", ['size' => 50, 'tabindex' => 1]) ?></td>
            </tr>
            <tr>
              <th>
                <label for="image_source"><?= $this->t('.source') ?></label>
              </th>
              <td>
                <?= $this->textField('image', 'source', ['size' => 50, 'tabindex' => 2]) ?>
              </td>
            </tr>
            <tr>
              <th></th>
              <td>
                <?= $this->submitTag($this->t('.add_do')) ?>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php }) ?>

  </div>
</div>

<div style="width: 100%; overflow: auto;">
  <?= $block ?>
</div>

<div id="inline-edit">
  <?php foreach ($this->inline->inline_images as $image) : ?>
    <?= $this->formTag(['action' => "delete_image"], ['id' => "delete-image-" . $image->id], function () use ($image) { ?>
      <?= $this->hiddenFieldTag("id", $image->id) ?>
    <?php }) ?>
  <?php endforeach ?>

  <?= $this->formTag('#update', function () { ?>
    <label for="inline_description"><?= $this->t('.description') ?>:</label>
    <br>
    <span id="inline-description">
      <a id="inline-description-edit-button" href="#" onclick="show_edit_desc(); return false;">
        <?= $this->format_text($this->inline->description) ?>

        <?php if (!$this->inline->description) {
            echo $this->t('.add_set_description_info');
        } ?>
      </a>
      <br>
    </span>

    <div id="inline-description-edit" style="display: none;">
      <?= $this->textArea('inline', 'description', ['size' => "40x5"]) ?>
    </div>
    <br>

    <?php foreach ($this->inline->inline_images as $image) : ?>
      <div id="inline-<?= $image->id ?>" class="inline-image-entry" style="margin-bottom: 1.5em;">
        <div style="height: 1.6em;">
          <span style="line-height: 1.6em;">
          <?php if (current_user()->has_permission($this->inline)) : ?>
            <?= $this->linkTo($this->t('.remove'), "#", ['onclick' => "if(confirm('" . $this->t('.remove_confirm') . "')) $('delete-image-" . $image->id . "').submit(); return false;"]) ?>
            | <?= $this->linkToFunction($this->t('.move.up'), "orderShift(" . $image->id . ", -1)") ?>
            | <?= $this->linkToFunction($this->t('.move.down'), "orderShift(" . $image->id . ", +1)") ?>
            |
            <a href="#" id="image-description-<?= $image->id ?>" onclick="show_edit_inline_desc(<?= $image->id ?>); return false;">
              <?php if (!$image->description) : ?>
                <?= $this->t('.add_image_description') ?>
              <?php else: ?>
              <?= $this->h($image->description) ?>
              <?php endif ?>
            </a>

            <span id="image-description-edit-<?= $image->id ?>" style="display: none;">
              <?= $this->textFieldTag("image[" . $image->id . "][description]", $image->description, ['size' => 40, 'disabled' => (!current_user()->has_permission($this->inline))]) ?>
            </span>
          <?php else: ?>
            <?= $this->h($image->description) ?>
          <?php endif ?>
          </span>
        </div>
        <img style="display: inline" src="<?= $image->preview_url() ?>" width="<?= $image->preview_dimensions()['width'] ?>" height="<?= $image->preview_dimensions()['height'] ?>"></img>

        <?= $this->hiddenFieldTag("image[" . $image->id . "][sequence]", $image->sequence, ['size' => 10, 'disabled' => (!current_user()->has_permission($this->inline)), 'class' => "inline-sequence"]) ?>
        <div>
        </div>

      </div>
    <?php endforeach ?>

    <?= $this->hiddenFieldTag("id", $this->inline->id) ?>
    <?= $this->submitTag($this->t('.save'), ['disabled' => (!current_user()->has_permission($this->inline))]) ?>
  <?php }) ?>
</div>

</div>
<script>
<?= $script ?>
InlineImage.init();

function show_post_add()
{
  $("post-add").show();
  $("post-add-button").hide();
}

function show_edit_desc()
{
  $("inline-description-edit").show();
  $("inline-description").hide();
  $("inline-description-edit").down("textarea").focus();
}

function show_edit_inline_desc(id)
{
  $("image-description-" + id).hide();
  $("image-description-edit-" + id).show();
  $("image-description-edit-" + id).down("input").focus();
}

function orderShift(id, direction) {
  var first_image = $("inline-" + id);
  var second_image;
  if(direction > 0)
  {
    var sibs = first_image.nextSiblings();
    second_image = sibs[0];
  }
  else
  {
    second_image = first_image;
    var sibs = first_image.previousSiblings();
    first_image = sibs[0];
  }
  if(!first_image || !second_image)
    return;

  {
    var swap = first_image.down(".inline-sequence").value;
    first_image.down(".inline-sequence").value = second_image.down(".inline-sequence").value;
    second_image.down(".inline-sequence").value = swap;
  }
  var parentNode = second_image.parentNode;
  parentNode.removeChild(second_image);
  parentNode.insertBefore(second_image, first_image);
}

</script>

<?= $this->formTag(['action' => "delete"], ['id' => "delete-group"], function () { ?>
  <?= $this->hiddenFieldTag("id", $this->inline->id) ?>
<?php }) ?>
<?= $this->formTag(['action' => "copy"], ['id' => "copy-group"], function () { ?>
  <?= $this->hiddenFieldTag("id", $this->inline->id) ?>
<?php }) ?>

<?php $this->contentFor('subnavbar', function () { ?>
  <?php if (current_user()->has_permission($this->inline)) : ?>
    <li><?= $this->linkTo($this->t('.delete'), "#", ['onclick' => "if(confirm('" . $this->t('.delete_confirm') . "')) $('delete-group').submit(); return false;"]) ?></li>
    <li><?= $this->linkTo($this->t('.crop'), ['action' => "crop", 'id' => $this->inline->id]) ?></li>
  <?php endif ?>

  <li><?= $this->linkTo($this->t('.copy'), "#", ['onclick' => "$('copy-group').submit(); return false;", 'level' => 'member']) ?></li>
<?php }) ?>

<?= $this->partial("footer") ?>
