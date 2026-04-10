<h2><?= $this->t('forum.new.title') ?></h2>

<div style="margin: 1em 0;">
  <div id="preview" class="response-list" style="display: none;">
  </div>

  <div id="reply" style="clear: both;">
    <?= $this->formTag(['action' => "create"], function () { ?>
      <?= $this->hiddenField("forum_post", "parent_id", ['value' => $this->params()->parent_id]) ?>
      <table>
        <tr>
          <td><label for="forum_post_title"><?= $this->t('forum.new.post_title') ?></label></td>
          <td><?= $this->textField('forum_post', 'title', ['size' => 60]) ?></td>
        </tr>
        <tr>
          <td colspan="2"><?= $this->textArea('forum_post', 'body', ['rows' => 20, 'cols' => 80]) ?></td>
        </tr>
        <tr>
          <td colspan="2"><?= $this->submitTag($this->t('forum.new.post')) ?><input name="preview" onclick="new Ajax.Updater('preview', '<?= $this->urlFor('#preview') ?>', {asynchronous:true, evalScripts:true, onSuccess:function(request){$('preview').show()}, parameters:Form.serialize(this.form)});" type="button" value="<?= $this->t('forum.new.preview') ?>"/></td>
        </tr>
      </table>
    <?php }) ?>
  </div>

</div>

<?php $this->contentFor('subnavbar', function () { ?>
  <li><?= $this->linkTo($this->t('forum.new.list'), ['action' => "index"]) ?></li>
  <li><?= $this->linkTo($this->t('forum.new.help'), ['controller' => "help", 'action' => "forum"]) ?></li>
<?php }) ?>

<script type="text/javascript">
  $("forum_post_title").focus();
</script>
