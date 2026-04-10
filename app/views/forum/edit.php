<div id="preview" style="display: none; margin: 1em 0; width: 60em;">
</div>

<?= $this->formTag("#update", function () { ?> 
  <?= $this->hiddenFieldTag("id", $this->params()->id) ?> 
  <?php if ($this->params()->page) : ?>
  <?= $this->hiddenFieldTag("page", (int) $this->params()->page, ['id' => '']) ?> 
  <?php endif ?>
  <table>
    <tr><td><label for="forum_post_title"><?= $this->t('.post_title') ?></label></td><td><?= $this->textField('forum_post', 'title', ['size' => 60]) ?></td></tr>
    <tr><td colspan="2"><?= $this->textArea('forum_post', 'body', ['rows' => 20, 'cols' => 80]) ?></td></tr>
    <tr><td colspan="2">
      <?= $this->submitTag($this->t('.post')) ?>
      <input name="preview" onclick="new Ajax.Updater('preview', '<?= $this->urlFor('#preview') ?>', {asynchronous:true, evalScripts:true, onSuccess:function(request){$('preview').show()}, parameters:Form.serialize(this.form)});" type="button" value="<?= $this->t('.preview') ?>"/>
    </td></tr>
  </table>
<?php }) ?>
